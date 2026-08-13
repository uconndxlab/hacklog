<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OllamaService
{
    public function __construct(protected HacklogReadOnlyToolService $toolService)
    {
    }

    /**
     * @return array{ok: bool, response?: string, error?: string}
     */
    public function generate(string $prompt): array
    {
        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model = (string) config('ollama.model', '');
        $path = (string) config('ollama.generate_path', '/api/generate');
        $timeout = (int) config('ollama.timeout_seconds', 30);
        $connectTimeout = (int) config('ollama.connect_timeout_seconds', 5);

        if ($baseUrl === '' || $model === '') {
            Log::warning('Ollama configuration is incomplete.', [
                'has_base_url' => $baseUrl !== '',
                'has_model' => $model !== '',
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama is not configured. Please check environment settings.',
            ];
        }

        $endpoint = $baseUrl.'/'.ltrim($path, '/');

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->post($endpoint, [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'think' => false,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Ollama request failed with transport error.', [
                'endpoint' => $endpoint,
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'Could not connect to Ollama. Verify that the local Ollama server is running.',
            ];
        }

        if ($response->failed()) {
            Log::warning('Ollama request returned non-success status.', [
                'endpoint' => $endpoint,
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama request failed. Check model availability and server status.',
            ];
        }

        $text = trim((string) $response->json('response', ''));

        if ($text === '') {
            Log::warning('Ollama request returned an empty response.', [
                'endpoint' => $endpoint,
                'model' => $model,
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama returned an empty response.',
            ];
        }

        return [
            'ok' => true,
            'response' => $text,
        ];
    }

    /**
     * @return array{ok: bool, response?: string, debug?: array<string, mixed>, error?: string}
     */
    public function chatWithTools(User $actor, string $prompt, bool $debug = false): array
    {
        $correlationId = (string) Str::uuid();
        $requestStartedAt = microtime(true);

        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model = (string) config('ollama.model', '');
        $path = (string) config('ollama.chat_path', '/api/chat');
        $timeout = (int) config('ollama.timeout_seconds', 90);
        $connectTimeout = (int) config('ollama.connect_timeout_seconds', 5);
        $maxRounds = max(1, (int) config('ollama.max_tool_rounds', 4));

        if ($baseUrl === '' || $model === '') {
            Log::warning('Ollama configuration is incomplete.', [
                'has_base_url' => $baseUrl !== '',
                'has_model' => $model !== '',
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama is not configured. Please check environment settings.',
            ];
        }

        $endpoint = $baseUrl.'/'.ltrim($path, '/');
        $toolDefinitions = $this->toolService->getToolDefinitions();

        Log::info('Hacklog AI: request started', [
            'correlation_id' => $correlationId,
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'available_tools' => count($toolDefinitions),
            'timeout_seconds' => $timeout,
            'max_tool_rounds' => $maxRounds,
        ]);

        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You are Hacklog AI, an assistant for the Hacklog project management application.',
                    'Only use data returned by the provided tools. Do not invent or estimate Hacklog data.',
                    'When tool results are empty, say no matching records were found.',
                    'Choose the tool whose data directly supports the user\'s question:',
                    '  - For urgency, overdue work, deadline pressure, priority, or questions about "this week": use get_project_urgency_summary.',
                    '  - For backlog size, volume, or workload: use count_open_tasks_by_project.',
                    '  - Do not substitute open task count as a proxy for urgency — they measure different things.',
                    'If no available tool can answer the question, say so briefly rather than using a proxy metric.',
                    'Keep answers concise and direct. Do not expose tool-selection reasoning in your reply.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        $toolDebug = [];
        $allToolsCalled = [];

        for ($round = 0; $round < $maxRounds; $round++) {
            $chatResult = $this->postChatRequest(
                $endpoint, $model, $messages, $toolDefinitions, $timeout, $connectTimeout,
                $correlationId, $round
            );

            if (!$chatResult['ok']) {
                $elapsed = round(microtime(true) - $requestStartedAt, 3);
                Log::warning('Hacklog AI: request failed at chat step', [
                    'correlation_id' => $correlationId,
                    'round' => $round,
                    'elapsed_seconds' => $elapsed,
                    'error' => $chatResult['error'] ?? 'unknown',
                ]);

                return $chatResult;
            }

            $message = $chatResult['message'];
            $messages[] = $message;

            $toolCalls = $this->extractToolCalls($message);

            if (empty($toolCalls)) {
                $finalText = trim((string) ($message['content'] ?? ''));

                if ($finalText === '') {
                    $elapsed = round(microtime(true) - $requestStartedAt, 3);
                    Log::warning('Hacklog AI: empty final response', [
                        'correlation_id' => $correlationId,
                        'round' => $round,
                        'elapsed_seconds' => $elapsed,
                    ]);

                    return [
                        'ok' => false,
                        'error' => 'Ollama returned an empty response.',
                    ];
                }

                $elapsed = round(microtime(true) - $requestStartedAt, 3);
                Log::info('Hacklog AI: request completed', [
                    'correlation_id' => $correlationId,
                    'total_rounds' => $round + 1,
                    'total_elapsed_seconds' => $elapsed,
                    'tools_called' => $allToolsCalled,
                    'final_response_length' => strlen($finalText),
                ]);

                return [
                    'ok' => true,
                    'response' => $finalText,
                    'debug' => $debug ? ['tool_calls' => $toolDebug] : null,
                ];
            }

            foreach ($toolCalls as $toolCall) {
                $toolName = (string) ($toolCall['function']['name'] ?? '');
                $arguments = $this->decodeToolArguments($toolCall['function']['arguments'] ?? []);
                $toolCallId = (string) ($toolCall['id'] ?? '');

                Log::info('Hacklog AI: tool requested', [
                    'correlation_id' => $correlationId,
                    'round' => $round,
                    'tool' => $toolName,
                    'arguments' => $arguments,
                ]);

                $toolStartedAt = microtime(true);
                $execution = $this->toolService->execute($actor, $toolName, $arguments);
                $toolElapsed = round(microtime(true) - $toolStartedAt, 3);

                $resultPayload = $execution['result'] ?? null;
                $resultCount = null;
                if (is_array($resultPayload)) {
                    foreach (['rows', 'tasks', 'users'] as $countKey) {
                        if (isset($resultPayload[$countKey]) && is_array($resultPayload[$countKey])) {
                            $resultCount = count($resultPayload[$countKey]);
                            break;
                        }
                    }
                }

                $serializedSize = strlen((string) json_encode($execution, JSON_UNESCAPED_SLASHES));

                if (!($execution['ok'] ?? false)) {
                    Log::warning('Hacklog AI: tool execution failed', [
                        'correlation_id' => $correlationId,
                        'round' => $round,
                        'tool' => $toolName,
                        'elapsed_seconds' => $toolElapsed,
                        'result_size_bytes' => $serializedSize,
                        'error' => $execution['error'] ?? 'unknown',
                    ]);
                } else {
                    Log::info('Hacklog AI: tool executed', [
                        'correlation_id' => $correlationId,
                        'round' => $round,
                        'tool' => $toolName,
                        'elapsed_seconds' => $toolElapsed,
                        'result_size_bytes' => $serializedSize,
                        'result_count' => $resultCount,
                    ]);
                }

                $allToolsCalled[] = $toolName;

                $toolPayload = [
                    'ok' => $execution['ok'] ?? false,
                    'tool' => $toolName,
                    'validated_arguments' => $execution['validated_arguments'] ?? $arguments,
                    'result' => $execution['result'] ?? null,
                    'error' => $execution['error'] ?? null,
                ];

                $toolDebug[] = [
                    'tool' => $toolName,
                    'validated_arguments' => $toolPayload['validated_arguments'],
                    'result' => $toolPayload['result'],
                    'error' => $toolPayload['error'],
                ];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $toolName,
                    'content' => json_encode($toolPayload, JSON_UNESCAPED_SLASHES),
                ];
            }

            $elapsed = round(microtime(true) - $requestStartedAt, 3);
            Log::info('Hacklog AI: continuing agent loop', [
                'correlation_id' => $correlationId,
                'completed_round' => $round,
                'next_round' => $round + 1,
                'total_elapsed_seconds' => $elapsed,
            ]);
        }

        $elapsed = round(microtime(true) - $requestStartedAt, 3);
        Log::warning('Hacklog AI: max tool rounds reached', [
            'correlation_id' => $correlationId,
            'max_rounds' => $maxRounds,
            'total_elapsed_seconds' => $elapsed,
            'tools_called' => $allToolsCalled,
        ]);

        return [
            'ok' => false,
            'error' => 'Ollama did not return a final response within tool-call limits.',
            'debug' => $debug ? ['tool_calls' => $toolDebug] : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array{ok: bool, message?: array<string, mixed>, error?: string}
     */
    protected function postChatRequest(
        string $endpoint,
        string $model,
        array $messages,
        array $tools,
        int $timeout,
        int $connectTimeout,
        string $correlationId = '',
        int $round = 0
    ): array {
        $think = false;
        $approxPayloadBytes = strlen((string) json_encode([
            'model' => $model,
            'stream' => false,
            'think' => $think,
            'messages' => $messages,
            'tools' => $tools,
        ], JSON_UNESCAPED_SLASHES));

        Log::info('Hacklog AI: sending chat request', [
            'correlation_id' => $correlationId,
            'round' => $round,
            'message_count' => count($messages),
            'tool_count' => count($tools),
            'approx_payload_bytes' => $approxPayloadBytes,
            'think' => $think,
        ]);

        $callStartedAt = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->post($endpoint, [
                    'model' => $model,
                    'stream' => false,
                    'think' => $think,
                    'messages' => $messages,
                    'tools' => $tools,
                ]);
        } catch (\Throwable $exception) {
            $elapsed = round(microtime(true) - $callStartedAt, 3);
            $isTimeout = stripos($exception->getMessage(), 'timeout') !== false
                || stripos($exception->getMessage(), 'timed out') !== false;

            Log::warning('Hacklog AI: ' . ($isTimeout ? 'chat request timed out' : 'chat request transport error'), [
                'correlation_id' => $correlationId,
                'round' => $round,
                'elapsed_seconds' => $elapsed,
                'exception_class' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'Could not connect to Ollama. Verify that the local Ollama server is running.',
            ];
        }

        $elapsed = round(microtime(true) - $callStartedAt, 3);

        if ($response->failed()) {
            $serverError = trim((string) $response->json('error', ''));

            Log::warning('Hacklog AI: chat request non-2xx response', [
                'correlation_id' => $correlationId,
                'round' => $round,
                'elapsed_seconds' => $elapsed,
                'status' => $response->status(),
                'server_error' => $serverError,
            ]);

            if ($serverError !== '' && str_contains(strtolower($serverError), 'does not support tools')) {
                return [
                    'ok' => false,
                    'error' => 'Configured model does not support tool calling. Choose a tools-capable Ollama model for Hacklog AI data questions.',
                ];
            }

            if ($serverError !== '') {
                return [
                    'ok' => false,
                    'error' => 'Ollama error: '.$serverError,
                ];
            }

            return [
                'ok' => false,
                'error' => 'Ollama request failed. Check model availability and server status.',
            ];
        }

        $message = $response->json('message');

        if (!is_array($message)) {
            Log::warning('Hacklog AI: malformed response - missing message payload', [
                'correlation_id' => $correlationId,
                'round' => $round,
                'elapsed_seconds' => $elapsed,
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 300),
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama returned an invalid response payload.',
            ];
        }

        $toolCalls = $this->extractToolCalls($message);
        $hasToolCalls = !empty($toolCalls);
        $toolCallNames = array_map(fn ($tc) => (string) ($tc['function']['name'] ?? ''), $toolCalls);

        $ollamaTiming = [];
        foreach (['total_duration', 'load_duration', 'prompt_eval_count', 'prompt_eval_duration', 'eval_count', 'eval_duration'] as $key) {
            $val = $response->json($key);
            if ($val !== null) {
                $ollamaTiming[$key] = $val;
            }
        }

        Log::info('Hacklog AI: chat response received', [
            'correlation_id' => $correlationId,
            'round' => $round,
            'elapsed_seconds' => $elapsed,
            'status' => $response->status(),
            'has_tool_calls' => $hasToolCalls,
            'tool_call_names' => $toolCallNames,
            'final_content_length' => $hasToolCalls ? null : strlen(trim((string) ($message['content'] ?? ''))),
            'ollama_timing' => $ollamaTiming ?: null,
        ]);

        return [
            'ok' => true,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return array<int, array<string, mixed>>
     */
    protected function extractToolCalls(array $message): array
    {
        $toolCalls = $message['tool_calls'] ?? [];
        return is_array($toolCalls) ? $toolCalls : [];
    }

    /**
     * @param mixed $arguments
     * @return array<string, mixed>
     */
    protected function decodeToolArguments(mixed $arguments): array
    {
        if (is_array($arguments)) {
            return $arguments;
        }

        if (is_string($arguments) && trim($arguments) !== '') {
            $decoded = json_decode($arguments, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
