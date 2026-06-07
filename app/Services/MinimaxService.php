<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MinimaxService
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
        protected ?string $model = null,
    ) {
        $this->apiKey = $this->apiKey ?? config('services.minimax.key', env('MINIMAX_API_KEY'));
        $this->baseUrl = rtrim($this->baseUrl ?? config('services.minimax.base_url', env('MINIMAX_BASE_URL', 'https://api.minimax.io/v1')), '/');
        $this->model = $this->model ?? config('services.minimax.model', env('MINIMAX_MODEL', 'MiniMax-M2.7'));
    }

    /**
     * Llama a la API de chat de MiniMax (compatible con OpenAI).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, tokens_input: ?int, tokens_output: ?int, raw: array}
     */
    public function chat(array $messages, float $temperature = 0.85): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Falta la API key de MiniMax. Define MINIMAX_API_KEY en tu .env');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
            'stream' => false,
            // MiniMax M2.7/M3 separan el "razonamiento" en reasoning_details
            // y devuelven SOLO la respuesta final en `content`.
            'reasoning_split' => true,
        ];

        /** @var PendingRequest $request */
        $request = Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(120);

        $response = $request->post($this->baseUrl . '/chat/completions', $payload);

        if (! $response->successful()) {
            Log::error('Minimax API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'Error de la API MiniMax: HTTP ' . $response->status() . ' — ' . substr($response->body(), 0, 500)
            );
        }

        $data = $response->json();
        $content = data_get($data, 'choices.0.message.content', '');

        // Filtro de seguridad: si el modelo aún incluye bloques <think>…</think>
        // (algunas versiones/fallbacks lo hacen), los eliminamos del contenido.
        $content = preg_replace('/<think>.*?<\/think>/s', '', (string) $content);
        $content = trim((string) $content);

        $usage = $data['usage'] ?? [];

        return [
            'content' => (string) $content,
            'tokens_input' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'tokens_output' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            'raw' => $data,
        ];
    }

    /**
     * Versión streaming de chat(): invoca a la API con `stream: true`
     * y va llamando a $onDelta con cada fragmento de contenido recibido.
     *
     * Al finalizar, devuelve el contenido acumulado y los tokens.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, tokens_input: ?int, tokens_output: ?int}
     */
    public function streamChat(array $messages, callable $onDelta, float $temperature = 0.85): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Falta la API key de MiniMax. Define MINIMAX_API_KEY en tu .env');
        }

        $client = new \GuzzleHttp\Client([
            'timeout' => 180,
            'connect_timeout' => 30,
        ]);

        /** @var \GuzzleHttp\Psr7\Response $response */
        $response = $client->post($this->baseUrl . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $temperature,
                'stream' => true,
                'reasoning_split' => true,
            ],
            'stream' => true,
        ]);

        $body = $response->getBody();
        $content = '';
        $tokensInput = null;
        $tokensOutput = null;
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(1024);
            if ($chunk === '' || $chunk === false) {
                usleep(10000);
                continue;
            }
            $buffer .= $chunk;

            // Procesa líneas completas terminadas en \n
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = rtrim($line, "\r");

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));
                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $data = json_decode($payload, true);
                if (! is_array($data)) {
                    continue;
                }

                $delta = data_get($data, 'choices.0.delta.content');
                if (is_string($delta) && $delta !== '') {
                    // Filtro de seguridad por si llega un bloque <think>…</think> en streaming
                    $clean = preg_replace('/<think>.*?<\/think>/s', '', $delta) ?? '';
                    if ($clean !== '') {
                        $content .= $clean;
                        $onDelta($clean);
                    }
                }

                if (isset($data['usage']) && is_array($data['usage'])) {
                    $tokensInput = isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : null;
                    $tokensOutput = isset($data['usage']['completion_tokens']) ? (int) $data['usage']['completion_tokens'] : null;
                }
            }
        }

        return [
            'content' => $content,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
        ];
    }
}
