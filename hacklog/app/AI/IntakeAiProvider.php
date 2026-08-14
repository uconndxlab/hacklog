<?php

namespace App\AI;

/**
 * Contract for AI providers that power the Hacklog AI Intake workflow.
 *
 * A provider receives a pre-built system prompt (compact project context)
 * and the user's source content, and returns a structured set of raw
 * task proposals. Normalization / DB-validation of IDs is handled upstream
 * by ProjectIntakeService after this call returns.
 */
interface IntakeAiProvider
{
    /**
     * Analyze intake content and return raw structured proposals.
     *
     * @param  string  $systemPrompt  Compact project context built by ProjectIntakeService.
     * @param  string  $userContent   Source text submitted by the user (meeting notes, email, etc.).
     * @return array{
     *   ok: bool,
     *   provider?: string,
     *   model?: string,
     *   summary?: string,
     *   proposals?: array<int, array<string, mixed>>,
     *   error?: string,
     * }
     */
    public function analyze(string $systemPrompt, string $userContent): array;
}
