<?php

namespace App\Services;

/**
 * Maps normalized Slack message text to a supported Hacklog bot intent.
 *
 * Deliberately simple keyword matching for V1.
 * Structured so an AI classifier can replace or supplement this later
 * without changing intent handlers.
 */
class SlackIntentMatcher
{
    const INTENT_DUE_THIS_WEEK = 'tasks_due_this_week';
    const INTENT_OVERDUE       = 'overdue_tasks';
    const INTENT_OPEN          = 'open_tasks';

    /**
     * Ordered intent rules: first match wins.
     * Each rule is a list of substrings; any match triggers the intent.
     *
     * @var array<string, string[]>
     */
    private const RULES = [
        self::INTENT_DUE_THIS_WEEK => [
            'due this week',
            'due next',
            'due soon',
            'what\'s due',
            'whats due',
            'due today',
        ],
        self::INTENT_OVERDUE => [
            'overdue',
            'past due',
            'what are we late',
            'what\'s late',
            'whats late',
            'behind on',
            'we\'re late',
            'we are late',
        ],
        self::INTENT_OPEN => [
            'open tasks',
            'open task',
            'still open',
            'still need',
            'outstanding',
            'what\'s left',
            'whats left',
            'what is left',
            'show tasks',
            'list tasks',
            'what needs',
            'not done',
        ],
    ];

    /**
     * Match the cleaned message text to a supported intent.
     * Returns null if no intent is recognized.
     */
    public static function match(string $message): ?string
    {
        $normalized = strtolower(trim($message));

        foreach (self::RULES as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $intent;
                }
            }
        }

        return null;
    }

    /**
     * All supported intent identifiers.
     *
     * @return string[]
     */
    public static function allIntents(): array
    {
        return [
            self::INTENT_DUE_THIS_WEEK,
            self::INTENT_OVERDUE,
            self::INTENT_OPEN,
        ];
    }
}
