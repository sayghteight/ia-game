<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Message;
use App\Services\GameStateParser;
use App\Services\MinimaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function __construct(
        protected MinimaxService $minimax,
        protected GameStateParser $stateParser,
    ) {}

    public function index()
    {
        $games = Game::orderByDesc('updated_at')->limit(20)->get();
        return view('game.index', compact('games'));
    }

    public function create()
    {
        return view('game.create');
    }

    public function store(Request $request)
    {
        // La llamada a MiniMax puede tardar más que el max_execution_time por defecto (30s).
        set_time_limit(180);

        $data = $request->validate([
            'title' => 'required|string|max:120',
            'character_name' => 'required|string|max:60',
            'character_class' => 'nullable|string|max:40',
        ]);

        $game = Game::create([
            'title' => $data['title'],
            'character_name' => $data['character_name'],
            'character_class' => $data['character_class'] ?? null,
            'hp' => 20,
            'hp_max' => 20,
            'gold' => 10,
            'level' => 1,
            'experience' => 0,
            'location' => 'Inicio',
            'inventory' => ['Mochila de cuero', 'Daga oxidada', 'Antorcha', 'Manta de viaje'],
            'status_notes' => 'Comienzo de la aventura.',
        ]);

        // Mensaje de sistema con el prompt del GameMaster
        $systemPrompt = $this->buildSystemPrompt($game);
        Message::create([
            'game_id' => $game->id,
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        // Primer turno: el "usuario" empuja la IA a narrar el inicio
        Message::create([
            'game_id' => $game->id,
            'role' => 'user',
            'content' => "Comienza la aventura. Narra el arranque para {$game->character_name}, {$game->character_class}. Termina con el bloque [STATE] inicial.",
        ]);

        $messages = $this->buildMessagesPayload($game);
        $this->callGameMaster($game, $messages, $isFirstTurn = true);

        return redirect()->route('games.show', $game);
    }

    public function show(Game $game)
    {
        $game->load('messages');
        return view('game.show', compact('game'));
    }

    public function act(Request $request, Game $game)
    {
        // La llamada a MiniMax puede tardar más que el max_execution_time por defecto (30s).
        set_time_limit(180);

        $data = $request->validate([
            'action' => 'required|string|min:1|max:2000',
        ]);

        // El jugador actúa primero
        Message::create([
            'game_id' => $game->id,
            'role' => 'user',
            'content' => $data['action'],
        ]);

        $messages = $this->buildMessagesPayload($game);
        $this->callGameMaster($game, $messages);

        return redirect()->route('games.show', $game);
    }

    /**
     * Versión streaming de act(): devuelve Server-Sent Events con cada
     * fragmento de la respuesta del GameMaster, para que el frontend
     * pueda ir pintando el texto según llega.
     */
    public function stream(Request $request, Game $game)
    {
        set_time_limit(180);

        $data = $request->validate([
            'action' => 'required|string|min:1|max:2000',
        ]);

        // Guardamos el mensaje del usuario ANTES de empezar a streamear.
        // Si esto falla, devolvemos una respuesta JSON normal (no SSE) para
        // que el cliente reciba un error estructurado.
        try {
            Message::create([
                'game_id' => $game->id,
                'role' => 'user',
                'content' => $data['action'],
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo guardar el mensaje del usuario', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->stream(function () use ($game) {
            // CRÍTICO bajo FrankenPHP / SAPI que bufferizan: una vez que
            // hacemos `echo`, no se pueden enviar más cabeceras. Si una
            // excepción se "escapa" del callback, el handler de errores de
            // Laravel intenta renderizar una página de error y falla con
            // "headers already sent". Solución: blindar el callback
            // totalmente (suprimir warnings, capturar TODO, no loguear
            // nada que pueda tirar de disco/BD).
            @ini_set('display_errors', '0');
            @ini_set('html_errors', '0');
            @ini_set('log_errors', '0');
            $oldReporting = error_reporting();
            error_reporting(0);

            // Limpiar cualquier buffer previo y desactivar el buffering.
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            @ob_implicit_flush(true);
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');

            // Restaurar el nivel de error al final (incluso si hay fatal).
            register_shutdown_function(function () use ($oldReporting) {
                @error_reporting($oldReporting);
            });

            $sse = function (array $payload): void {
                try {
                    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                    @ob_flush();
                    @flush();
                } catch (\Throwable $ignored) {
                    // Nada que hacer: estamos en un stream y no podemos
                    // reportar nada sin romper la respuesta.
                }
            };

            $fullContent = '';

            try {
                $sse(['type' => 'start']);

                $result = $this->minimax->streamChat(
                    $this->buildMessagesPayload($game),
                    function (string $delta) use (&$fullContent, $sse): void {
                        $fullContent .= $delta;
                        $sse(['type' => 'delta', 'content' => $delta]);
                    }
                );

                // Parsea el bloque [STATE] y actualiza el estado del juego
                $state = $this->stateParser->extract($fullContent);
                if ($state) {
                    $update = [];
                    if (! is_null($state['hp'])) $update['hp'] = $state['hp'];
                    if (! is_null($state['hp_max'])) $update['hp_max'] = $state['hp_max'];
                    if (! is_null($state['xp'])) $update['experience'] = $state['xp'];
                    if (! is_null($state['gold'])) $update['gold'] = $state['gold'];
                    if (! is_null($state['location'])) $update['location'] = $state['location'];
                    if (! is_null($state['inventory'])) $update['inventory'] = $state['inventory'];
                    if (! is_null($state['notes'])) $update['status_notes'] = $state['notes'];
                    if (! empty($update)) {
                        $game->update($update);
                    }
                }

                $msg = Message::create([
                    'game_id' => $game->id,
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'tokens_input' => $result['tokens_input'],
                    'tokens_output' => $result['tokens_output'],
                ]);

                $sse([
                    'type' => 'done',
                    'message_id' => $msg->id,
                    'tokens_input' => $result['tokens_input'],
                    'tokens_output' => $result['tokens_output'],
                    'state' => $state,
                ]);
            } catch (\Throwable $e) {
                // Log blindado: si el log tira (BD caída, disco lleno…)
                // no debe poder escapar y reventar el stream.
                try {
                    Log::error('GameMaster stream error', [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
                    // nada
                }
                $sse(['type' => 'error', 'message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function destroy(Game $game)
    {
        $game->delete();
        return redirect()->route('games.index')->with('status', 'Partida eliminada.');
    }

    public function promptEdit()
    {
        $path = $this->promptPath();
        $content = File::exists($path) ? File::get($path) : '';
        return view('game.prompt', ['content' => $content, 'path' => $path]);
    }

    public function promptUpdate(Request $request)
    {
        $data = $request->validate([
            'content' => 'required|string|min:20',
        ]);
        File::put($this->promptPath(), $data['content']);
        return redirect()->route('games.prompt.edit')->with('status', 'Prompt actualizado.');
    }

    // ---------- Helpers ----------

    protected function promptPath(): string
    {
        return storage_path('app/gamemaster_prompt.md');
    }

    protected function buildSystemPrompt(Game $game): string
    {
        $base = File::exists($this->promptPath())
            ? File::get($this->promptPath())
            : 'Eres un GameMaster de RPG.';

        $characterBlock = "\n\n## Datos del personaje del jugador\n"
            . "- Nombre: {$game->character_name}\n"
            . "- Clase: " . ($game->character_class ?: 'Aventurero') . "\n"
            . "- Nivel: {$game->level}\n"
            . "- HP: {$game->hp}/{$game->hp_max}\n"
            . "- Oro: {$game->gold}\n"
            . "- XP: {$game->experience}\n"
            . "- Inventario: " . implode(', ', $game->inventory_list) . "\n";

        $stateBlock = "\n## Estado de la partida (CANON, no lo cambies sin motivo)\n"
            . "- Ubicación actual: {$game->location}\n"
            . "- Notas del mundo: " . ($game->status_notes ?: 'Sin notas aún.') . "\n\n"
            . "Recuerda: tu narración de este turno debe ocurrir en '{$game->location}' "
            . "salvo que el jugador se desplace de forma explícita.\n";

        return $base . $characterBlock . $stateBlock;
    }

    /**
     * Construye el array de mensajes para enviar a MiniMax
     * a partir de los mensajes persistidos.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessagesPayload(Game $game): array
    {
        // Empezamos con el system prompt ACTUAL (del archivo, para que
        // ediciones al prompt se reflejen) y luego la conversación.
        $payload = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($game)],
        ];

        foreach ($game->messages()->where('role', '!=', 'system')->get() as $msg) {
            $payload[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        return $payload;
    }

    /**
     * Llama al GameMaster, guarda su respuesta y actualiza el estado del juego.
     */
    protected function callGameMaster(Game $game, array $messages, bool $isFirstTurn = false): void
    {
        $result = $this->minimax->chat($messages);

        Message::create([
            'game_id' => $game->id,
            'role' => 'assistant',
            'content' => $result['content'],
            'tokens_input' => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
        ]);

        $state = $this->stateParser->extract($result['content']);
        if ($state) {
            $update = [];
            if (! is_null($state['hp'])) $update['hp'] = $state['hp'];
            if (! is_null($state['hp_max'])) $update['hp_max'] = $state['hp_max'];
            if (! is_null($state['xp'])) $update['experience'] = $state['xp'];
            if (! is_null($state['gold'])) $update['gold'] = $state['gold'];
            if (! is_null($state['location'])) $update['location'] = $state['location'];
            if (! is_null($state['inventory'])) $update['inventory'] = $state['inventory'];
            if (! is_null($state['notes'])) $update['status_notes'] = $state['notes'];

            if (! empty($update)) {
                $game->update($update);
            }
        }
    }
}
