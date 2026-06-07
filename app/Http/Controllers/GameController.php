<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Message;
use App\Models\Player;
use App\Services\GameStateParser;
use App\Services\MinimaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function __construct(
        protected MinimaxService $minimax,
        protected GameStateParser $stateParser,
    ) {}

    // -------- Páginas principales --------

    public function index()
    {
        // Mostrar las partidas donde el jugador actual ha participado
        $myPlayerIds = $this->myPlayerIds();
        $games = Game::with('creator')
            ->whereIn('id', function ($q) use ($myPlayerIds) {
                $q->select('game_id')->from('players')->whereIn('id', $myPlayerIds);
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return view('game.index', compact('games'));
    }

    public function create()
    {
        return view('game.create');
    }

    public function store(Request $request)
    {
        set_time_limit(180);
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'name' => 'required|string|max:60',
            'character_name' => 'required|string|max:60',
            'character_class' => 'nullable|string|max:40',
            'pin' => 'nullable|digits:4',
        ]);

        $game = Game::create([
            'code' => $this->generateUniqueCode(),
            'pin' => $data['pin'] ?? $this->generatePin(),
            'title' => $data['title'],
            'current_round' => 1,
            'location' => 'Inicio',
            'world_notes' => 'Comienzo de la aventura.',
        ]);

        $player = $this->createPlayer($game, $data, isCreator: true);

        // Mensaje de sistema con prompt del GM (se inyecta al construirse el payload)
        // y un primer "user" del GM para arrancar la partida.
        Message::create([
            'game_id' => $game->id,
            'player_id' => null,
            'role' => 'user',
            'content' => "Comienza la aventura. Narra el arranque del mundo para el grupo de héroes. Termina con el bloque [STATE] inicial (world).",
            'round' => 1,
            'status' => 'pending',
        ]);

        $this->callGameMaster($game, true);

        return $this->redirectToGame($game, $player);
    }

    public function joinForm()
    {
        return view('game.join');
    }

    public function join(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
            'pin' => 'required|digits:4',
            'name' => 'required|string|max:60',
            'character_name' => 'required|string|max:60',
            'character_class' => 'nullable|string|max:40',
        ]);

        $game = Game::where('code', strtoupper($data['code']))->first();
        if (! $game) {
            return back()->withErrors(['code' => 'No existe ninguna partida con ese código.'])->withInput();
        }
        if ($game->pin !== $data['pin']) {
            return back()->withErrors(['pin' => 'PIN incorrecto.'])->withInput();
        }

        $player = $this->createPlayer($game, $data, isCreator: false);

        return $this->redirectToGame($game, $player);
    }

    public function show(Request $request, Game $game)
    {
        $game->load(['players', 'messages.player']);
        $currentPlayer = $this->getCurrentPlayer($request, $game);

        if (! $currentPlayer) {
            return redirect()->route('games.join.form', ['code' => $game->code])
                ->withErrors(['code' => 'Necesitas unirte a esta partida primero.']);
        }

        return view('game.show', [
            'game' => $game,
            'currentPlayer' => $currentPlayer,
        ]);
    }

    public function act(Request $request, Game $game)
    {
        $currentPlayer = $this->getCurrentPlayer($request, $game);
        if (! $currentPlayer) {
            return response()->json(['error' => 'No perteneces a esta partida.'], 403);
        }

        $data = $request->validate([
            'action' => 'required|string|min:1|max:2000',
        ]);

        Message::create([
            'game_id' => $game->id,
            'player_id' => $currentPlayer->id,
            'role' => 'user',
            'content' => "[{$currentPlayer->character_name}]: {$data['action']}",
            'round' => $game->current_round,
            'status' => 'pending',
        ]);

        return back();
    }

    public function resolve(Request $request, Game $game)
    {
        return $this->stream($request, $game);
    }

    public function stream(Request $request, Game $game)
    {
        set_time_limit(180);

        $currentPlayer = $this->getCurrentPlayer($request, $game);
        if (! $currentPlayer) {
            return response()->json(['error' => 'No perteneces a esta partida.'], 403);
        }

        // Si no hay acciones pendientes, no hacemos nada (devolvemos un SSE vacío con error suave).
        $pending = $game->pendingMessages()->orderBy('created_at')->get();
        if ($pending->isEmpty()) {
            return response()->stream(function () {
                echo "data: " . json_encode(['type' => 'error', 'message' => 'No hay acciones pendientes para resolver.']) . "\n\n";
                @ob_flush(); @flush();
            }, 200, ['Content-Type' => 'text/event-stream; charset=utf-8']);
        }

        return response()->stream(function () use ($game, $pending) {
            // ---- Blindaje total del callback (FrankenPHP) ----
            @ini_set('display_errors', '0');
            @ini_set('html_errors', '0');
            @ini_set('log_errors', '0');
            $oldReporting = error_reporting();
            error_reporting(0);
            register_shutdown_function(function () use ($oldReporting) {
                @error_reporting($oldReporting);
            });

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            @ob_implicit_flush(true);
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');

            $sse = function (array $payload): void {
                try {
                    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                    @ob_flush();
                    @flush();
                } catch (\Throwable $ignored) {
                }
            };

            $fullContent = '';
            $round = $game->current_round;

            try {
                $sse(['type' => 'start', 'round' => $round]);

                $messages = $this->buildMessagesPayload($game);

                $result = $this->minimax->streamChat(
                    $messages,
                    function (string $delta) use (&$fullContent, $sse): void {
                        $fullContent .= $delta;
                        $sse(['type' => 'delta', 'content' => $delta]);
                    }
                );

                // 1) Parsear estado y actualizar MUNDO
                $state = $this->stateParser->extract($fullContent);
                if ($state) {
                    $update = [];
                    if (! is_null($state['location'])) $update['location'] = $state['location'];
                    if (! is_null($state['notes'])) $update['world_notes'] = $state['notes'];
                    if (! empty($update)) {
                        $game->update($update);
                    }
                }

                // 2) Guardar respuesta del GM
                $msg = Message::create([
                    'game_id' => $game->id,
                    'player_id' => null,
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'round' => $round,
                    'status' => 'resolved',
                    'tokens_input' => $result['tokens_input'],
                    'tokens_output' => $result['tokens_output'],
                ]);

                // 3) Marcar las acciones pendientes como resueltas
                Message::whereIn('id', $pending->pluck('id'))->update(['status' => 'resolved']);

                // 4) Avanzar a la siguiente ronda
                $game->update(['current_round' => $round + 1]);

                $sse([
                    'type' => 'done',
                    'message_id' => $msg->id,
                    'round' => $round,
                    'next_round' => $round + 1,
                    'tokens_input' => $result['tokens_input'],
                    'tokens_output' => $result['tokens_output'],
                    'state' => $state,
                ]);
            } catch (\Throwable $e) {
                try {
                    Log::error('GameMaster stream error', [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
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

    public function destroy(Request $request, Game $game)
    {
        $currentPlayer = $this->getCurrentPlayer($request, $game);
        if (! $currentPlayer || ! $currentPlayer->is_creator) {
            return response()->json(['error' => 'Solo el creador puede eliminar la partida.'], 403);
        }
        $game->delete();
        Cookie::queue(Cookie::forget($this->cookieName($game)));
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

    // -------- Helpers internos --------

    protected function promptPath(): string
    {
        return storage_path('app/gamemaster_prompt.md');
    }

    protected function cookieName(Game $game): string
    {
        return 'player_' . $game->code;
    }

    protected function getCurrentPlayer(Request $request, Game $game): ?Player
    {
        $token = $request->cookie($this->cookieName($game));
        if (! $token) return null;
        return $game->players()->where('session_token', $token)->first();
    }

    protected function myPlayerIds(): array
    {
        // Lee TODAS las cookies player_* y devuelve los ids de jugador
        // correspondientes. Útil para el listado del index.
        $tokens = [];
        foreach (Cookie::get() as $name => $value) {
            if (str_starts_with($name, 'player_') && is_string($value) && strlen($value) > 10) {
                $tokens[] = $value;
            }
        }
        if (empty($tokens)) return [-1]; // imposible, así el whereIn nunca matchea
        return Player::whereIn('session_token', $tokens)->pluck('id')->all() ?: [-1];
    }

    protected function createPlayer(Game $game, array $data, bool $isCreator): Player
    {
        return Player::create([
            'game_id' => $game->id,
            'name' => $data['name'],
            'character_name' => $data['character_name'],
            'character_class' => $data['character_class'] ?? null,
            'hp' => 20,
            'hp_max' => 20,
            'gold' => 10,
            'level' => 1,
            'xp' => 0,
            'inventory' => ['Mochila de cuero', 'Daga oxidada', 'Antorcha', 'Manta de viaje'],
            'session_token' => Str::random(48),
            'is_creator' => $isCreator,
        ]);
    }

    protected function redirectToGame(Game $game, Player $player)
    {
        Cookie::queue(
            Cookie::make($this->cookieName($game), $player->session_token, 60 * 24 * 30, null, null, false, true)
        );
        return redirect()->route('games.show', ['game' => $game->code]);
    }

    protected function generateUniqueCode(int $maxAttempts = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin I,O,0,1 para evitar confusión
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            for ($j = 0; $j < 6; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (! Game::where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new \RuntimeException('No se pudo generar un código único.');
    }

    protected function generatePin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    protected function buildSystemPrompt(Game $game): string
    {
        $base = File::exists($this->promptPath())
            ? File::get($this->promptPath())
            : 'Eres un GameMaster de RPG.';

        $playersBlock = "\n\n## Party actual\n";
        foreach ($game->players as $p) {
            $playersBlock .= "- {$p->character_name}"
                . " (" . ($p->character_class ?: 'Aventurero') . ")"
                . " · HP {$p->hp}/{$p->hp_max} · Oro {$p->gold} · Nv {$p->level}"
                . " · Inventario: " . implode(', ', $p->inventory_list) . "\n";
        }

        $stateBlock = "\n## Estado del mundo (CANON)\n"
            . "- Ubicación: {$game->location}\n"
            . "- Notas: " . ($game->world_notes ?: 'Sin notas aún.') . "\n"
            . "- Ronda actual: {$game->current_round}\n";

        return $base . $playersBlock . $stateBlock;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessagesPayload(Game $game): array
    {
        $payload = [['role' => 'system', 'content' => $this->buildSystemPrompt($game)]];

        foreach ($game->messages()->orderBy('created_at')->orderBy('id')->get() as $msg) {
            if ($msg->role === 'system') continue;
            $payload[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        return $payload;
    }

    protected function callGameMaster(Game $game, bool $isFirstTurn = false): void
    {
        $messages = $this->buildMessagesPayload($game);
        $result = $this->minimax->chat($messages);
        $round = $game->current_round;

        Message::create([
            'game_id' => $game->id,
            'player_id' => null,
            'role' => 'assistant',
            'content' => $result['content'],
            'round' => $round,
            'status' => 'resolved',
            'tokens_input' => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
        ]);

        // Marcar pendientes como resueltos y avanzar ronda
        Message::where('game_id', $game->id)
            ->where('status', 'pending')
            ->update(['status' => 'resolved']);
        $game->update(['current_round' => $round + 1]);

        $state = $this->stateParser->extract($result['content']);
        if ($state) {
            $update = [];
            if (! is_null($state['location'])) $update['location'] = $state['location'];
            if (! is_null($state['notes'])) $update['world_notes'] = $state['notes'];
            if (! empty($update)) {
                $game->update($update);
            }
        }
    }
}
