<?php

namespace App\Jobs;

use App\AI\SlackIntentClassifier;
use App\Models\Project;
use App\Models\ProjectIntake;
use App\Models\User;
use App\Services\SlackBotService;
use App\Services\SlackIntentMatcher;
use App\Services\SlackIdentityService;
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
        SlackQueryService $queryService,
        SlackIdentityService $identityService
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

        // Strip <@BOT_ID> mentions and normalize whitespace before command matching.
        // This happens before project resolution because a Slack identity belongs to
        // a Hacklog user globally, not to a particular project.
        $cleanedText = preg_replace('/<@[A-Z0-9]+>/i', '', $rawText);
        $cleanedText = (string) preg_replace('/\s+/', ' ', (string) $cleanedText);
        $cleanedText = trim($cleanedText);

        if (($netid = $identityService->netidFromCommand($cleanedText)) !== null) {
            $this->handleIdentityLink($channelId, $threadTs, $userId, $netid, $botService, $identityService);
            return;
        }

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

        // Determine whether this message is a reply inside an existing thread.
        // Used by the capture handler and supplied as context to the AI classifier.
        $messageTs   = (string) ($event['ts'] ?? '');
        $rawThreadTs = (string) ($event['thread_ts'] ?? '');
        $isInThread  = $rawThreadTs !== '' && $rawThreadTs !== $messageTs;

        // Bare mention with no text — show help without an AI call.
        if ($cleanedText === '') {
            $botService->postMessage($channelId, $this->respondUnknown(), $threadTs ?: null);
            Log::info('Slack bot: bare mention — showing help.', ['event_id' => $this->eventId]);
            return;
        }

        // ── Step 1: Deterministic keyword matching (fast, no AI) ──────────────
        $intent = SlackIntentMatcher::match($cleanedText);

        Log::info('Slack bot: intent matched.', [
            'event_id'  => $this->eventId,
            'intent'    => $intent ?? 'none',
            'text'      => $cleanedText,
            'via'       => 'deterministic',
        ]);

        // ── Step 2: AI fallback for unrecognized phrases ───────────────────────
        if ($intent === null) {
            $classifier = new SlackIntentClassifier();
            $aiIntent   = $classifier->classify($cleanedText, $project->name, $isInThread);

            // Map help/unknown/null to null so they fall through to respondUnknown()
            if ($aiIntent !== null
                && $aiIntent !== 'unknown'
                && $aiIntent !== 'help'
                && in_array($aiIntent, SlackIntentMatcher::allIntents(), true)) {
                $intent = $aiIntent;

                Log::info('Slack bot: intent resolved via AI classifier.', [
                    'event_id' => $this->eventId,
                    'intent'   => $intent,
                ]);
            }
        }

        // Build and post response
        $response = match ($intent) {
            SlackIntentMatcher::INTENT_DUE_THIS_WEEK  => $this->respondDueThisWeek($project, $queryService),
            SlackIntentMatcher::INTENT_OVERDUE         => $this->respondOverdue($project, $queryService),
            SlackIntentMatcher::INTENT_MY_OPEN          => $this->respondMyOpen($project, $userId, $cleanedText, $queryService),
            SlackIntentMatcher::INTENT_OPEN            => $this->respondOpen($project, $queryService),
            SlackIntentMatcher::INTENT_CREATE_INTAKE   => null, // handled separately below
            default                                    => $this->respondUnknown(),
        };

        if ($intent === SlackIntentMatcher::INTENT_CREATE_INTAKE) {
            $this->handleCreateIntake($project, $event, $botService);
            return;
        }

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

    private function respondMyOpen(Project $project, string $slackId, string $cleanedText, SlackQueryService $service): string
    {
        $user = User::where('slack_id', $slackId)->where('active', true)->first();

        if (!$user) {
            return "I don't know which Hacklog user you are yet. Reply with `@Hacklog I am yourNetID` to link your account.";
        }

        $thisProjectOnly = SlackIntentMatcher::isCurrentProjectOnly($cleanedText);
        $result = $service->openForUser($user, $thisProjectOnly ? $project : null, limit: 10);
        $total = $result['total'];
        $tasks = $result['tasks'];

        if ($total === 0) {
            return $thisProjectOnly
                ? "You have no open {$project->name} tasks. :white_check_mark:"
                : 'You have no open tasks. :white_check_mark:';
        }

        $noun = $total === 1 ? 'task' : 'tasks';
        $shown = count($tasks);
        $header = $thisProjectOnly
            ? "*{$total} open {$project->name} {$noun} assigned to you*"
            : "*{$total} open {$noun} assigned to you*";
        $lines = [$header];

        $currentProjectName = null;
        foreach ($tasks as $task) {
            if (! $thisProjectOnly && $task['project_name'] !== $currentProjectName) {
                $currentProjectName = $task['project_name'];
                $lines[] = "*{$currentProjectName}*";
            }
            $lines[] = '• '.$task['title'];
        }

        if ($total > $shown) {
            $more = $total - $shown;
            $moreLine = "…and {$more} more";
            if ($thisProjectOnly) {
                $moreLine .= ' — <'.route('projects.board', $project).'|View board>';
            }
            $lines[] = $moreLine;
        }

        return implode("\n", $lines);
    }

    private function respondUnknown(): string
    {
        return "I can currently answer these questions or take these actions for this Hacklog project:\n"
            . "• *I am yourNetID* — link your Slack and Hacklog accounts\n"
            . "• *my tasks* — open tasks assigned to you across projects (`in this project` to limit)\n"
            . "• *tasks due this week* — what's coming up\n"
            . "• *overdue tasks* — what's past due\n"
            . "• *open tasks* — everything still in progress\n"
            . "• *turn this into tasks* — (reply in a thread) analyze the thread and propose Hacklog tasks";
    }

    private function handleIdentityLink(
        string $channelId,
        string $threadTs,
        string $slackId,
        string $netid,
        SlackBotService $botService,
        SlackIdentityService $identityService
    ): void {
        if ($channelId === '' || $slackId === '') {
            Log::warning('Slack bot: identity command missing Slack event identifiers.', [
                'event_id' => $this->eventId,
            ]);
            return;
        }

        $result = $identityService->link($slackId, $netid);

        $message = match ($result['status']) {
            SlackIdentityService::LINKED => "You're linked to *{$result['user']->name}* (`{$result['user']->netid}`). I can now use your Slack identity for personalized Hacklog requests.",
            SlackIdentityService::ALREADY_LINKED => "You're already linked to *{$result['user']->name}* (`{$result['user']->netid}`).",
            SlackIdentityService::USER_NOT_FOUND => "I couldn't find an active Hacklog user with NetID `{$netid}`. Check the NetID or ask a Hacklog admin to create/activate the account.",
            SlackIdentityService::SLACK_ALREADY_LINKED => 'Your Slack account is already linked to a different Hacklog user. Ask a Hacklog admin to change it.',
            SlackIdentityService::USER_ALREADY_LINKED => "The Hacklog user `{$netid}` is already linked to another Slack account. Ask a Hacklog admin to change it.",
        };

        $botService->postMessage($channelId, $message, $threadTs ?: null);

        Log::info('Slack bot: identity link command handled.', [
            'event_id' => $this->eventId,
            'slack_id' => $slackId,
            'netid' => $netid,
            'status' => $result['status'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Capture intent: create a Slack-originated AI Intake
    // -------------------------------------------------------------------------

    /**
     * Handle the create_ai_intake_from_slack intent.
     *
     * Top-level message: use any content after the command keyword, or ask user to use a thread.
     * Thread reply: fetch the full thread and assemble source content.
     * Creates a ProjectIntake, dispatches ProcessProjectIntakeJob, acknowledges in Slack.
     */
    private function handleCreateIntake(Project $project, array $event, SlackBotService $botService): void
    {
        $channelId = (string) ($event['channel'] ?? '');
        $userId    = (string) ($event['user'] ?? '');
        $messageTs = (string) ($event['ts'] ?? '');
        $threadTs  = (string) ($event['thread_ts'] ?? '');
        $rawText   = (string) ($event['text'] ?? '');

        // Thread to reply in (start a new thread from the command message if top-level)
        $replyThreadTs = $threadTs ?: $messageTs;

        // Idempotency guard: do not create a second intake if this event was somehow retried
        if (ProjectIntake::where('correlation_id', $this->eventId)->exists()) {
            Log::info('Slack bot: intake already exists for this event, skipping.', [
                'event_id' => $this->eventId,
            ]);
            return;
        }

        // Determine whether this is a reply inside an existing thread
        $isThreadReply = $threadTs !== '' && $threadTs !== $messageTs;

        $sourceContent = '';

        if ($isThreadReply) {
            // Fetch the full thread and assemble source content
            $messages      = $botService->fetchThreadMessages($channelId, $threadTs);
            $sourceContent = $this->assembleThreadContent($messages, $messageTs);
        } else {            // Top-level: try to extract content from the message itself
            $stripped = preg_replace('/<@[A-Z0-9]+>/i', '', $rawText);
            $stripped = (string) $stripped;

            // Remove capture command keywords to isolate any trailing content
            $commandPhrases = [
                'add this as a task', 'add this to hacklog', 'turn this into tasks',
                'turn this into a task', 'turn these into tasks', 'turn these into a task',
                'capture this', 'send this to hacklog',
                'log this', 'make this a task', 'create tasks from this', 'put this in hacklog',
            ];
            foreach ($commandPhrases as $phrase) {
                $stripped = str_ireplace($phrase, '', $stripped);
            }
            $stripped = trim($stripped, " \t\n\r\0\x0B:,.-");

            if (mb_strlen($stripped) >= 10) {
                $sourceContent = $stripped;
            }
        }

        if (mb_strlen($sourceContent) < 10) {
            if ($isThreadReply) {
                // We're already in a thread but couldn't fetch its content.
                // Most likely cause: the bot lacks the channels:history (public) or
                // groups:history (private channel) OAuth scope.
                $guidance = "I can see this thread but couldn't read its messages. "
                    . "Ask a workspace admin to verify the Hacklog bot has *channels:history* "
                    . "(and *groups:history* for private channels) in Slack's OAuth scopes, "
                    . "then reinstall the app and try again.";
            } else {
                // Top-level message with no usable content alongside the command.
                $guidance = "To capture content into *{$project->name}*, reply with this command "
                    . "inside the Slack thread you want analyzed — or include the notes alongside your mention.";
            }

            $botService->postMessage($channelId, $guidance, $replyThreadTs ?: null);

            Log::warning('Slack bot: capture intent — insufficient source content.', [
                'event_id'   => $this->eventId,
                'channel_id' => $channelId,
                'is_thread'  => $isThreadReply,
            ]);
            return;
        }

        $hacklogUserId = User::where('slack_id', $userId)
            ->where('active', true)
            ->value('id');

        // Persist the intake using the existing AI Intake pipeline
        $intake = ProjectIntake::create([
            'project_id'     => $project->id,
            'user_id'        => $hacklogUserId,
            'source_type'    => ProjectIntake::SOURCE_TYPE_SLACK,
            'source_content' => $sourceContent,
            'status'         => ProjectIntake::STATUS_QUEUED,
            'correlation_id' => $this->eventId,  // Slack event ID doubles as correlation ID
            'slack_context'  => [
                'channel_id' => $channelId,
                'thread_ts'  => $isThreadReply ? $threadTs : $messageTs,
                'message_ts' => $messageTs,
                'user_id'    => $userId,
                'event_id'   => $this->eventId,
            ],
        ]);

        // Dispatch the same AI Intake analysis job used by the web UI
        ProcessProjectIntakeJob::dispatch($intake->id);

        Log::info('Slack bot: intake created from Slack.', [
            'event_id'      => $this->eventId,
            'intake_id'     => $intake->id,
            'project_id'    => $project->id,
            'source_length' => mb_strlen($sourceContent),
            'is_thread'     => $isThreadReply,
        ]);

        // Immediate acknowledgment — analysis continues asynchronously
        $ack = "Got it — I'm analyzing this thread for *{$project->name}* and will post back when the proposed tasks are ready.";
        $botService->postMessage($channelId, $ack, $replyThreadTs ?: null);
    }

    /**
     * Assemble human-readable source content from Slack thread messages.
     *
     * Excludes: bot messages, empty messages, the triggering command message.
     * Strips bot/channel mentions from retained messages.
     *
     * @param  array<int, array<string, mixed>>  $messages  From conversations.replies
     * @param  string  $triggerTs  Timestamp of the @hacklog command message (to exclude)
     */
    private function assembleThreadContent(array $messages, string $triggerTs): string
    {
        $lines = [];

        foreach ($messages as $msg) {
            // Only plain messages
            if (($msg['type'] ?? '') !== 'message') {
                continue;
            }
            // Skip all bot messages (including our own)
            if (!empty($msg['bot_id'])) {
                continue;
            }
            // Skip the triggering @hacklog command itself
            if (($msg['ts'] ?? '') === $triggerTs) {
                continue;
            }

            $text = trim((string) ($msg['text'] ?? ''));

            // Strip bot/user/channel mentions
            $text = preg_replace('/<@[A-Z0-9]+>/i', '', $text);
            $text = preg_replace('/<#[A-Z0-9]+(?:\|[^>]*)?>/', '', $text);
            $text = trim((string) $text);

            if ($text === '') {
                continue;
            }

            $lines[] = $text;
        }

        return implode("\n\n", $lines);
    }
}
