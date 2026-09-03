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
    const INTENT_DUE_THIS_WEEK  = 'tasks_due_this_week';
    const INTENT_OVERDUE         = 'overdue_tasks';
    const INTENT_OPEN            = 'open_tasks';
    const INTENT_MY_OPEN         = 'my_open_tasks';
    const INTENT_CREATE_INTAKE   = 'create_ai_intake_from_slack';

    /**
     * Ordered intent rules: first match wins.
     * Each rule is a list of substrings; any match triggers the intent.
     *
     * @var array<string, string[]>
     */
    private const RULES = [
        // Capture intent checked first — keywords are distinct from query intents.
        self::INTENT_CREATE_INTAKE => [
            // Short shorthands — these are unambiguous and handled deterministically
            'task me',
            'task this',
            'grab this',
            'make actionable',
            'make this actionable',
            // Longer explicit phrases
            'add this as a task',
            'add this as tasks',
            'add this to hacklog',
            'turn this into tasks',
            'turn this into a task',
            'turn this thread',        // covers "turn this thread into tasks/a task"
            'turn this into',          // covers any "turn this into …" variant
            'turn these into',         // covers plural "turn these into …" variants
            'capture this',
            'send this to hacklog',
            'log this',
            'make this a task',
            'make this into a task',
            'create a task from this',
            'create tasks from this',
            'put this in hacklog',
        ],
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
        // Check personalized phrases before general open-task phrases.
        self::INTENT_MY_OPEN => [
            'my tasks',
            'my open tasks',
            'tasks assigned to me',
            'what is assigned to me',
            'what\'s assigned to me',
            'whats assigned to me',
            'what do i need to do',
            'what tasks do i have',
            'what do i have',
            'what can i do',
            'what can i work on',
            'what should i do',
            'whats open for me',
            'what\'s open for me',
            'what is open for me',
            'open for me',
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
     * Whether a "my tasks" message is limited to the current Slack channel's project.
     * Default is all projects.
     */
    public static function isCurrentProjectOnly(string $message): bool
    {
        $normalized = strtolower(trim($message));

        return str_contains($normalized, 'this project')
            || str_contains($normalized, 'this channel');
    }

    /**
     * All supported intent identifiers.
     *
     * @return string[]
     */
    public static function allIntents(): array
    {
        return [
            self::INTENT_CREATE_INTAKE,
            self::INTENT_DUE_THIS_WEEK,
            self::INTENT_OVERDUE,
            self::INTENT_MY_OPEN,
            self::INTENT_OPEN,
        ];
    }
}
