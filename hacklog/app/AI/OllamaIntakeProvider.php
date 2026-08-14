<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI Intake provider backed by a local Ollama server.
 *
 * Uses Ollama's /api/chat endpoint with structured-output format constraint
 * and think:true so the model reasons through the source before writing JSON.
 * Configuration is read from config/ollama.php (OLLAMA_* env vars).
 */
class OllamaIntakeProvider implements IntakeAiProvider
{
    public function analyze(string $systemPrompt, string $userContent): array
    {
        $correlationId = (string) Str::uuid();
        $startedAt     = microtime(true);

        $baseUrl        = rtrim((string) config('ollama.base_url', ''), '/');
        $model          = (string) config('ollama.model', '');
        $path           = (string) config('ollama.chat_path', '/api/chat');
        $timeout        = (int) config('ollama.timeout_seconds', 90);
        $connectTimeout = (int) config('ollama.connect_timeout_seconds', 5);

        if ($baseUrl === '' || $model === '') {
            return [
                'ok'    => false,
                'error' => 'Ollama is not configured. Check OLLAMA_BASE_URL and OLLAMA_MODEL.',
            ];
        }

        $endpoint = $baseUrl . '/' . ltrim($path, '/');

        $payload = [
            'model'   => $model,
            'stream'  => false,
            'think'   => true,
            'format'  => $this->jsonSchema(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userContent],
            ],
        ];

        Log::info('Hacklog AI: intake analysis started', [
            'provider'             => 'ollama',
            'correlation_id'       => $correlationId,
            'model'                => $model,
            'approx_payload_bytes' => strlen((string) json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ]);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->post($endpoint, $payload);
        } catch (\Throwable $exception) {
            $elapsed   = round(microtime(true) - $startedAt, 3);
            $isTimeout = stripos($exception->getMessage(), 'timeout') !== false
                || stripos($exception->getMessage(), 'timed out') !== false;

            Log::warning('Hacklog AI: intake ' . ($isTimeout ? 'request timed out' : 'transport error'), [
                'provider'        => 'ollama',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
                'exception_class' => get_class($exception),
                'error'           => $exception->getMessage(),
            ]);

            return [
                'ok'    => false,
                'error' => 'Could not connect to Ollama. Verify that the local Ollama server is running.',
            ];
        }

        $elapsed = round(microtime(true) - $startedAt, 3);

        if ($response->failed()) {
            $serverError = trim((string) $response->json('error', ''));

            Log::warning('Hacklog AI: intake non-2xx response', [
                'provider'        => 'ollama',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
                'status'          => $response->status(),
                'server_error'    => $serverError,
            ]);

            return [
                'ok'    => false,
                'error' => $serverError !== '' ? 'Ollama error: ' . $serverError : 'Ollama request failed. Check model availability.',
            ];
        }

        $content = trim((string) ($response->json('message.content') ?? ''));

        if ($content === '') {
            Log::warning('Hacklog AI: intake empty response', [
                'provider'        => 'ollama',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
            ]);

            return ['ok' => false, 'error' => 'Ollama returned an empty response.'];
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            Log::warning('Hacklog AI: intake response not valid JSON', [
                'provider'        => 'ollama',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
                'content_preview' => substr($content, 0, 300),
            ]);

            return ['ok' => false, 'error' => 'Ollama returned a response that could not be parsed as JSON.'];
        }

        Log::info('Hacklog AI: intake analysis completed', [
            'provider'        => 'ollama',
            'correlation_id'  => $correlationId,
            'elapsed_seconds' => $elapsed,
            'model'           => $model,
            'proposal_count'  => count($parsed['proposals'] ?? []),
        ]);

        return [
            'ok'        => true,
            'provider'  => 'ollama',
            'model'     => $model,
            'summary'   => trim((string) ($parsed['summary'] ?? '')),
            'proposals' => $parsed['proposals'] ?? [],
        ];
    }

    /**
     * JSON Schema passed to Ollama's format parameter for constrained generation.
     *
     * @return array<string, mixed>
     */
    private function jsonSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'summary'   => ['type' => 'string'],
                'proposals' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'title'                => ['type' => 'string'],
                            'description'          => ['type' => 'string'],
                            'suggested_phase_id'   => ['type' => 'integer'],
                            'due_date'             => ['type' => 'string'],
                            'confidence'           => ['type' => 'number'],
                            'source_excerpt'       => ['type' => 'string'],
                            'possible_duplicate_of' => ['type' => 'string'],
                        ],
                        'required' => ['title'],
                    ],
                ],
            ],
            'required' => ['summary', 'proposals'],
        ];
    }
}
