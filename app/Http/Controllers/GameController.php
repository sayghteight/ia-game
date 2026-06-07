<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Message;
use App\Services\GameStateParser;
use App\Services\MinimaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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
