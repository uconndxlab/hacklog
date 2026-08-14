<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI Intake provider backed by the OpenAI Chat Completions API.
 *
 * Uses OpenAI's strict structured outputs (json_schema response_format) to
 * extract task proposals from source text. Configuration is read from
 * config/ai.php (OPENAI_* env vars).
 *
 * Does NOT use tool calling — structured output is sufficient for intake.
 */
class OpenAiIntakeProvider implements IntakeAiProvider
{
    public function analyze(string $systemPrompt, string $userContent): array
    {
        $correlationId = (string) Str::uuid();
        $startedAt     = microtime(true);

        $apiKey  = (string) config('ai.openai.api_key', '');
        $model   = (string) config('ai.openai.model', 'gpt-4o-mini');
        $baseUrl = rtrim((string) config('ai.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) config('ai.openai.timeout_seconds', 60);

        if ($apiKey === '') {
            return [
                'ok'    => false,
                'error' => 'OpenAI is not configured. Check OPENAI_API_KEY.',
            ];
        }

        $endpoint = $baseUrl . '/chat/completions';

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userContent],
            ],
            'response_format' => [
                'type'        => 'json_schema',
                'json_schema' => $this->jsonSchema(),
            ],
        ];

        Log::info('Hacklog AI: intake analysis started', [
            'provider'       => 'openai',
            'correlation_id' => $correlationId,
            'model'          => $model,
        ]);

        try {
            $response = Http::timeout($timeout)
                ->withToken($apiKey)
                ->post($endpoint, $payload);
        } catch (\Throwable $exception) {
            $elapsed   = round(microtime(true) - $startedAt, 3);
            $isTimeout = stripos($exception->getMessage(), 'timeout') !== false
                || stripos($exception->getMessage(), 'timed out') !== false;

            Log::warning('Hacklog AI: intake ' . ($isTimeout ? 'request timed out' : 'transport error'), [
                'provider'        => 'openai',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
                'exception_class' => get_class($exception),
                'error'           => $exception->getMessage(),
            ]);

            return [
                'ok'    => false,
                'error' => 'Could not reach the OpenAI API. Check network connectivity and configuration.',
            ];
        }

        $elapsed = round(microtime(true) - $startedAt, 3);

        if ($response->failed()) {
            $apiError = trim((string) ($response->json('error.message') ?? $response->body()));

            Log::warning('Hacklog AI: intake non-2xx response', [
                'provider'        => 'openai',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
                'status'          => $response->status(),
                'api_error'       => substr($apiError, 0, 300),
            ]);

            return [
                'ok'    => false,
                'error' => $apiError !== '' ? 'OpenAI error: ' . $apiError : 'OpenAI request failed.',
            ];
        }

        // OpenAI returns choices[0].message.content as a JSON string
        $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

        if ($content === '') {
            Log::warning('Hacklog AI: intake empty response', [
                'provider'        => 'openai',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
            ]);

            return ['ok' => false, 'error' => 'OpenAI returned an empty response.'];
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            Log::warning('Hacklog AI: intake response not valid JSON', [
                'provider'        => 'openai',
                'correlation_id'  => $correlationId,
                'elapsed_seconds' => $elapsed,
                'content_preview' => substr($content, 0, 300),
            ]);

            return ['ok' => false, 'error' => 'OpenAI returned a response that could not be parsed as JSON.'];
        }

        Log::info('Hacklog AI: intake analysis completed', [
            'provider'        => 'openai',
            'correlation_id'  => $correlationId,
            'elapsed_seconds' => $elapsed,
            'model'           => $model,
            'proposal_count'  => count($parsed['proposals'] ?? []),
            'usage'           => $response->json('usage'),
        ]);

        return [
            'ok'        => true,
            'provider'  => 'openai',
            'model'     => $model,
            'summary'   => trim((string) ($parsed['summary'] ?? '')),
            'proposals' => $parsed['proposals'] ?? [],
        ];
    }

    /**
     * OpenAI strict JSON schema definition.
     * All properties are required and additionalProperties is false.
     *
     * @return array<string, mixed>
     */
    private function jsonSchema(): array
    {
        return [
            'name'   => 'intake_proposals',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['summary', 'proposals'],
                'properties'           => [
                    'summary'   => ['type' => 'string'],
                    'proposals' => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'required'             => ['title', 'description', 'suggested_phase_id', 'due_date', 'confidence', 'source_excerpt', 'possible_duplicate_of'],
                            'properties'           => [
                                'title'                => ['type' => 'string'],
                                'description'          => ['type' => ['string', 'null']],
                                'suggested_phase_id'   => ['type' => ['integer', 'null']],
                                'due_date'             => ['type' => ['string', 'null']],
                                'confidence'           => ['type' => 'number'],
                                'source_excerpt'       => ['type' => ['string', 'null']],
                                'possible_duplicate_of' => ['type' => ['string', 'null']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
