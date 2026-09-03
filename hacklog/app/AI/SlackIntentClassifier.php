<?php

namespace App\AI;

use App\Services\SlackIntentMatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered fallback intent classifier for Slack bot messages.
 *
 * Used only when deterministic keyword matching in SlackIntentMatcher returns null.
 * Returns one value from a strict allowlisted enum; never invokes Hacklog operations.
 *
 * Uses the same AI provider configured for the application (Ollama or OpenAI).
 * Falls back safely to null on any failure so the caller can show the help response.
 */
class SlackIntentClassifier
{
    /**
     * The only intent values this classifier may return.
     *
     * @return string[]
     */
    public static function allowedIntents(): array
    {
        return SlackIntentMatcher::classifierIntents();
    }

    /**
     * Classify a normalized Slack message into one of the allowed intents.
     *
     * Returns null on failure — callers must treat null the same as 'unknown'.
     *
     * @param  string  $message      Cleaned, whitespace-normalized user message.
     * @param  string  $projectName  Resolved Hacklog project name for context.
     * @param  bool    $isInThread   Whether this message is a reply in an existing thread.
     */
    public function classify(string $message, string $projectName, bool $isInThread): ?string
    {
        $startedAt = microtime(true);
        $provider  = (string) config('ai.provider', 'ollama');

        Log::info('Slack bot: AI intent classification started.', [
            'provider'     => $provider,
            'message'      => $message,
            'is_in_thread' => $isInThread,
            'project'      => $projectName,
        ]);

        try {
            $raw = match ($provider) {
                'openai' => $this->callOpenAI($this->systemPrompt(), $this->userContent($message, $projectName, $isInThread)),
                default  => $this->callOllama($this->systemPrompt(), $this->userContent($message, $projectName, $isInThread)),
            };
        } catch (\Throwable $exception) {
            Log::warning('Slack bot: AI intent classification threw exception.', [
                'exception_class' => get_class($exception),
                'error'           => $exception->getMessage(),
            ]);
            return null;
        }

        $elapsed = round(microtime(true) - $startedAt, 3);

        if ($raw === null) {
            Log::info('Slack bot: AI intent classification returned no result — falling back.', [
                'elapsed_seconds' => $elapsed,
                'provider'        => $provider,
            ]);
            return null;
        }

        $intent     = (string) ($raw['intent'] ?? 'unknown');
        $confidence = isset($raw['confidence']) ? round((float) $raw['confidence'], 2) : null;

        // Validate against allowlist to prevent any out-of-band values
        if (!in_array($intent, self::allowedIntents(), true)) {
            Log::warning('Slack bot: AI returned out-of-allowlist intent.', [
                'intent'   => $intent,
                'provider' => $provider,
            ]);
            $intent = 'unknown';
        }

        Log::info('Slack bot: AI intent classification completed.', [
            'elapsed_seconds' => $elapsed,
            'provider'        => $provider,
            'intent'          => $intent,
            'confidence'      => $confidence,
        ]);

        return $intent;
    }

    // -------------------------------------------------------------------------
    // Prompt construction
    // -------------------------------------------------------------------------

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You classify Slack commands sent to the Hacklog project management bot. Reply ONLY with JSON.

Allowed intents:
- tasks_due_this_week: asking about tasks due this week or soon (e.g. "what do we owe this week?", "anything coming up?", "what's due soon?", "what needs to be done by Friday?")
- overdue_tasks: asking about tasks that are late or past due (e.g. "what are we late on?", "anything past due?", "what are we behind on?")
- my_open_tasks: asking about the speaker's own assigned work (e.g. "what am I working on?", "what's on my plate?", "what should I work on?", "what do I have going on?"). Use this whenever the request is first-person or about "my" workload, even if it also sounds like open/outstanding work.
- open_tasks: asking what work is still open or outstanding for the project/team as a whole (e.g. "what's left?", "what's still outstanding?", "what needs doing?", "what's not done?"). Do not use this for first-person workload questions.
- create_ai_intake_from_slack: user wants to save, capture, or convert content into Hacklog tasks. This includes ANY of: "task me", "task this", "grab this", "save this", "capture", "make actionable", "put in Hacklog", "turn into work", "make tasks", "create tasks". Classify as this intent regardless of whether in_thread is yes or no — the handler will ask for content if needed.
- help: asking what the bot can do or how to use it
- unknown: anything else, clearly unrelated requests (e.g. "order me a pizza"), or genuinely unclear intent

Never invent or allow intent values outside this list.
PROMPT;
    }

    private function userContent(string $message, string $projectName, bool $isInThread): string
    {
        $inThread = $isInThread ? 'yes' : 'no';
        return "Message: \"{$message}\"\nIn thread: {$inThread}\nProject: {$projectName}";
    }

    // -------------------------------------------------------------------------
    // Provider-specific calls
    // -------------------------------------------------------------------------

    /**
     * @return array{intent: string, confidence?: float}|null
     */
    private function callOpenAI(string $systemPrompt, string $userContent): ?array
    {
        $apiKey  = (string) config('ai.openai.api_key', '');
        $model   = (string) config('ai.openai.model', 'gpt-4o-mini');
        $baseUrl = rtrim((string) config('ai.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            Log::warning('Slack bot: OpenAI not configured — cannot classify intent.');
            return null;
        }

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post($baseUrl . '/chat/completions', [
                'model'           => $model,
                'max_tokens'      => 60,
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userContent],
                ],
                'response_format' => [
                    'type'        => 'json_schema',
                    'json_schema' => $this->openAiSchema(),
                ],
            ]);

        if (!$response->successful() || !$response->json('choices.0.message.content')) {
            Log::warning('Slack bot: OpenAI classification request failed.', [
                'status' => $response->status(),
                'error'  => $response->json('error.message'),
            ]);
            return null;
        }

        $parsed = json_decode($response->json('choices.0.message.content'), true);
        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @return array{intent: string, confidence?: float}|null
     */
    private function callOllama(string $systemPrompt, string $userContent): ?array
    {
        $baseUrl = rtrim((string) config('ollama.base_url', ''), '/');
        $model   = (string) config('ollama.model', '');
        $path    = (string) config('ollama.chat_path', '/api/chat');

        if ($baseUrl === '' || $model === '') {
            Log::warning('Slack bot: Ollama not configured — cannot classify intent.');
            return null;
        }

        $response = Http::timeout(20)
            ->connectTimeout(5)
            ->post($baseUrl . '/' . ltrim($path, '/'), [
                'model'    => $model,
                'stream'   => false,
                'think'    => true,    // reasoning improves accuracy for short/ambiguous phrases
                'format'   => $this->ollamaSchema(),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userContent],
                ],
            ]);

        if (!$response->successful()) {
            Log::warning('Slack bot: Ollama classification request failed.', [
                'status'       => $response->status(),
                'server_error' => $response->json('error'),
            ]);
            return null;
        }

        $content = trim((string) ($response->json('message.content') ?? ''));
        if ($content === '') {
            return null;
        }

        $parsed = json_decode($content, true);
        return is_array($parsed) ? $parsed : null;
    }

    // -------------------------------------------------------------------------
    // JSON schemas
    // -------------------------------------------------------------------------

    /**
     * Ollama format parameter — simple schema (enum not enforced server-side by Ollama,
     * but the allowlist check in classify() guards against out-of-range values).
     *
     * @return array<string, mixed>
     */
    private function ollamaSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'intent'     => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['intent'],
        ];
    }

    /**
     * OpenAI strict json_schema — enum enforces the allowlist at the API level.
     *
     * @return array<string, mixed>
     */
    private function openAiSchema(): array
    {
        return [
            'name'   => 'slack_intent',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['intent', 'confidence'],
                'properties'           => [
                    'intent'     => [
                        'type' => 'string',
                        'enum' => self::allowedIntents(),
                    ],
                    'confidence' => ['type' => 'number'],
                ],
            ],
        ];
    }
}
