<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\SlackBotService;
use App\Services\SlackIntentMatcher;
use App\Services\SlackQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes a verified Slack app_mention event asynchronously.
 *
 * The HTTP controller responds to Slack immediately; this job does the
 * actual channel→project resolution, intent matching, query, and reply.
 *
 * Idempotency: the controller checks event_id in cache before dispatching,
 * so duplicate retries from Slack should not reach this job. $tries = 1
 * prevents Laravel from automatically retrying failures.
 */
class ProcessSlackEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Do not automatically retry. */
    public int $tries = 1;

    public function __construct(
        public readonly array  $payload,
        public readonly string $eventId
    ) {}

    public function handle(
        SlackBotService   $botService,
        SlackQueryService $queryService
    ): void {
        $event = $this->payload['event'] ?? [];

        // Secondary guard: only handle app_mention
        if (($event['type'] ?? '') !== 'app_mention') {
            return;
        }

        // Ignore messages from bots (including ourselves) to prevent loops
        if (!empty($event['bot_id'])) {
            return;
        }

        $channelId  = (string) ($event['channel'] ?? '');
        $rawText    = (string) ($event['text'] ?? '');
        $userId     = (string) ($event['user'] ?? '');
        // Reply in the originating thread; if top-level, start a thread from that message
        $threadTs   = (string) ($event['thread_ts'] ?? $event['ts'] ?? '');

        Log::info('Slack bot: processing app_mention.', [
            'event_id'   => $this->eventId,
            'channel_id' => $channelId,
            'user_id'    => $userId,
        ]);

        // Resolve channel → project
        $project = Project::where('slack_channel_id', $channelId)
            ->where('slack_bot_enabled', true)
            ->first();

        if (!$project) {
            // Check if the channel exists but the bot is disabled
            $exists = Project::where('slack_channel_id', $channelId)->exists();
            $message = $exists
                ? 'The Hacklog bot is not enabled for this channel.'
                : "This Slack channel isn't connected to a Hacklog project yet.";

            Log::info('Slack bot: channel not mapped to an active project.', [
                'event_id'   => $this->eventId,
                'channel_id' => $channelId,
            ]);

            $botService->postMessage($channelId, $message, $threadTs ?: null);
            return;
        }

        Log::info('Slack bot: project resolved.', [
            'event_id'   => $this->eventId,
            'channel_id' => $channelId,
            'project_id' => $project->id,
            'project'    => $project->name,
        ]);

        // Strip <@BOT_ID> mentions from the message text before intent matching
        $cleanedText = preg_replace('/<@[A-Z0-9]+>/i', '', $rawText);
        $cleanedText = trim((string) $cleanedText);

        // Match intent
        $intent = SlackIntentMatcher::match($cleanedText);

        Log::info('Slack bot: intent matched.', [
            'event_id' => $this->eventId,
            'intent'   => $intent ?? 'none',
            'text'     => $cleanedText,
        ]);

        // Build and post response
        $response = match ($intent) {
            SlackIntentMatcher::INTENT_DUE_THIS_WEEK => $this->respondDueThisWeek($project, $queryService),
            SlackIntentMatcher::INTENT_OVERDUE        => $this->respondOverdue($project, $queryService),
            SlackIntentMatcher::INTENT_OPEN           => $this->respondOpen($project, $queryService),
            default                                   => $this->respondUnknown(),
        };

        $botService->postMessage($channelId, $response, $threadTs ?: null);

        Log::info('Slack bot: response posted.', [
            'event_id'   => $this->eventId,
            'project_id' => $project->id,
            'intent'     => $intent ?? 'none',
        ]);
    }

    // -------------------------------------------------------------------------
    // Response builders
    // -------------------------------------------------------------------------

    private function respondDueThisWeek(Project $project, SlackQueryService $service): string
    {
        $tasks = $service->dueThisWeek($project);

        if (empty($tasks)) {
            return "No open {$project->name} tasks are due this week. :white_check_mark:";
        }

        $count = count($tasks);
        $noun  = $count === 1 ? 'task' : 'tasks';
        $lines = ["*{$count} {$project->name} {$noun} due this week*"];

        foreach ($tasks as $task) {
            $line = '• ' . $task['title'];
            if ($task['effective_due_date']) {
                $date = \Carbon\Carbon::parse($task['effective_due_date']);
                $label = match (true) {
                    $date->isToday()    => 'Today',
                    $date->isTomorrow() => 'Tomorrow',
                    default             => $date->format('D'),          // Mon, Tue …
                };
                $line .= " — {$label}";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function respondOverdue(Project $project, SlackQueryService $service): string
    {
        $tasks = $service->overdue($project);

        if (empty($tasks)) {
            return "No {$project->name} tasks are overdue. :white_check_mark:";
        }

        $count = count($tasks);
        $noun  = $count === 1 ? 'task is' : 'tasks are';
        $lines = ["*{$count} {$project->name} {$noun} overdue*"];

        foreach ($tasks as $task) {
            $line = '• ' . $task['title'];
            if ($task['days_overdue'] > 0) {
                $days = $task['days_overdue'];
                $line .= " — {$days} " . ($days === 1 ? 'day' : 'days') . ' overdue';
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function respondOpen(Project $project, SlackQueryService $service): string
    {
        $result = $service->open($project, limit: 10);
        $total  = $result['total'];
        $tasks  = $result['tasks'];

        if ($total === 0) {
            return "No open {$project->name} tasks. :white_check_mark:";
        }

        $noun  = $total === 1 ? 'task' : 'tasks';
        $shown = count($tasks);
        $boardUrl = route('projects.board', $project);

        $header = $total > $shown
            ? "*{$total} open {$project->name} {$noun}* — <{$boardUrl}|View board>"
            : "*{$total} open {$project->name} {$noun}*";

        $lines = [$header];

        foreach ($tasks as $task) {
            $lines[] = '• ' . $task['title'];
        }

        if ($total > $shown) {
            $more = $total - $shown;
            $lines[] = "…and {$more} more";
        }

        return implode("\n", $lines);
    }

    private function respondUnknown(): string
    {
        return "I can currently answer these questions about this Hacklog project:\n"
            . "• *tasks due this week* — what's coming up\n"
            . "• *overdue tasks* — what's past due\n"
            . "• *open tasks* — everything still in progress";
    }
}
