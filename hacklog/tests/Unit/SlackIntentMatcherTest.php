<?php

namespace Tests\Unit;

use App\Services\SlackIntentMatcher;
use Tests\TestCase;

/**
 * Unit tests for SlackIntentMatcher.
 *
 * Covers all five intents plus the unknown/fallback case.
 * These tests do NOT require a database or Slack credentials.
 */
class SlackIntentMatcherTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Capture intent (create_ai_intake_from_slack)
    // -------------------------------------------------------------------------

    /** @dataProvider captureIntentProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('captureIntentProvider')]
    public function test_capture_intent_recognized(string $text): void
    {
        $result = SlackIntentMatcher::match($text);

        $this->assertSame(
            SlackIntentMatcher::INTENT_CREATE_INTAKE,
            $result,
            "Expected capture intent for: \"{$text}\""
        );
    }

    public static function captureIntentProvider(): array
    {
        return [
            // Short shorthands added for deterministic matching
            'task me'                         => ['task me'],
            'task this'                       => ['task this'],
            'grab this'                       => ['grab this'],
            'make actionable'                 => ['make actionable'],
            'make this actionable'            => ['make this actionable'],
            // Existing explicit phrases
            'bare command'                    => ['add this as a task'],
            'plural tasks'                    => ['add this as tasks'],
            'with hey prefix'                 => ['hey add this as a task'],
            'with please prefix'              => ['please add this as a task'],
            'turn into tasks'                 => ['turn this into tasks'],
            'turn into a task'                => ['turn this into a task'],
            'turn these into tasks'           => ['please turn these into tasks'],
            'turn this thread'                => ['turn this thread into tasks'],
            'turn thread with can you prefix' => ['can you turn this thread into tasks'],
            'capture this'                    => ['capture this'],
            'capture with prefix'             => ['hey Hacklog capture this'],
            'send to hacklog'                 => ['send this to hacklog'],
            'make this a task'                => ['make this a task'],
            'make this into a task'           => ['make this into a task'],
            'create a task from this'         => ['create a task from this'],
            'create tasks from this'          => ['create tasks from this'],
            'add to hacklog'                  => ['add this to hacklog'],
            'mixed case'                      => ['ADD THIS AS A TASK'],
            'after whitespace normalization'  => ['hey  add this as a task'],   // double-space (post mention strip)
        ];
    }

    // -------------------------------------------------------------------------
    // Due-this-week intent
    // -------------------------------------------------------------------------

    /** @dataProvider dueThisWeekProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('dueThisWeekProvider')]
    public function test_due_this_week_intent_recognized(string $text): void
    {
        $this->assertSame(SlackIntentMatcher::INTENT_DUE_THIS_WEEK, SlackIntentMatcher::match($text));
    }

    public static function dueThisWeekProvider(): array
    {
        return [
            'any tasks due this week'  => ['any tasks due this week?'],
            'whats due'                => ['whats due'],
            'due soon'                 => ['anything due soon?'],
        ];
    }

    // -------------------------------------------------------------------------
    // Overdue intent
    // -------------------------------------------------------------------------

    /** @dataProvider overdueProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('overdueProvider')]
    public function test_overdue_intent_recognized(string $text): void
    {
        $this->assertSame(SlackIntentMatcher::INTENT_OVERDUE, SlackIntentMatcher::match($text));
    }

    public static function overdueProvider(): array
    {
        return [
            'overdue'          => ["what's overdue?"],
            'past due'         => ['anything past due?'],
            'behind on'        => ["what are we behind on?"],
        ];
    }

    // -------------------------------------------------------------------------
    // Open-tasks intent
    // -------------------------------------------------------------------------

    /** @dataProvider openTasksProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('openTasksProvider')]
    public function test_open_tasks_intent_recognized(string $text): void
    {
        $this->assertSame(SlackIntentMatcher::INTENT_OPEN, SlackIntentMatcher::match($text));
    }

    public static function openTasksProvider(): array
    {
        return [
            'any open tasks'   => ['any open tasks?'],
            'still open'       => ["what's still open?"],
            'outstanding'      => ['anything outstanding?'],
            'not done'         => ["what's not done?"],
        ];
    }

    /** @dataProvider myOpenTasksProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('myOpenTasksProvider')]
    public function test_my_open_tasks_intent_recognized(string $text): void
    {
        $this->assertSame(SlackIntentMatcher::INTENT_MY_OPEN, SlackIntentMatcher::match($text));
    }

    public static function myOpenTasksProvider(): array
    {
        return [
            'my tasks' => ['show me my tasks'],
            'assigned to me' => ["what's assigned to me?"],
            'need to do' => ['what do I need to do?'],
        ];
    }

    // -------------------------------------------------------------------------
    // Unknown / no match
    // -------------------------------------------------------------------------

    /** @dataProvider unknownProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('unknownProvider')]
    public function test_unknown_intent_returns_null(string $text): void
    {
        $this->assertNull(SlackIntentMatcher::match($text));
    }

    public static function unknownProvider(): array
    {
        return [
            'empty'         => [''],
            'just spaces'   => ['   '],
            'unrelated'     => ['hello there'],
            'team question' => ['who is working on the redesign?'],
        ];
    }

    // -------------------------------------------------------------------------
    // allIntents() completeness
    // -------------------------------------------------------------------------

    public function test_all_intents_includes_capture(): void
    {
        $this->assertContains(SlackIntentMatcher::INTENT_CREATE_INTAKE, SlackIntentMatcher::allIntents());
    }

    public function test_all_intents_returns_five_intents(): void
    {
        $this->assertCount(5, SlackIntentMatcher::allIntents());
    }
}
