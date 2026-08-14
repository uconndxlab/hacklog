<?php

namespace App\Jobs;

use App\Models\ProjectIntake;
use App\Models\ProjectIntakeProposal;
use App\Services\ProjectIntakeService;
use App\Services\SlackBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProjectIntakeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Run once only. On failure the intake is marked failed via failed().
     */
    public int $tries = 1;

    /**
     * Execution timeout. Must exceed OLLAMA_TIMEOUT_SECONDS.
     * Set DB_QUEUE_RETRY_AFTER >= 180 in .env to match.
     */
    public int $timeout = 180;

    public function __construct(public readonly int $intakeId)
    {
    }

    public function handle(ProjectIntakeService $service, SlackBotService $botService): void
    {
        $intake = ProjectIntake::with('project')->find($this->intakeId);

        if (!$intake) {
            Log::warning('Hacklog AI: intake job — record not found', [
                'intake_id' => $this->intakeId,
            ]);
            return;
        }

        // Guard: if somehow already reached a terminal state, do not re-process.
        if ($intake->isTerminal()) {
            return;
        }

        Log::info('Hacklog AI: intake job started', [
            'intake_id'      => $intake->id,
            'project_id'     => $intake->project_id,
            'correlation_id' => $intake->correlation_id,
        ]);

        $intake->update([
            'status'                 => ProjectIntake::STATUS_PROCESSING,
            'processing_started_at'  => now(),
        ]);

        $result = $service->analyze($intake->project, $intake->source_content);

        if (!$result['ok']) {
            $intake->update([
                'status'                    => ProjectIntake::STATUS_FAILED,
                'error_message'             => $result['error'] ?? 'Analysis failed.',
                'processing_completed_at'   => now(),
            ]);

            Log::warning('Hacklog AI: intake job — analysis failed', [
                'intake_id'      => $intake->id,
                'correlation_id' => $intake->correlation_id,
                'error'          => $result['error'] ?? 'unknown',
            ]);

            $this->notifySlack($intake, 0, failed: true, botService: $botService);
            return;
        }

        // Persist proposals
        $proposalCount = 0;
        foreach ($result['proposals'] as $data) {
            ProjectIntakeProposal::create([
                'project_intake_id'      => $intake->id,
                'title'                  => $data['title'],
                'description'            => $data['description'] ?? null,
                'suggested_phase_id'     => $data['suggested_phase_id'] ?? null,
                'suggested_assignee_id'  => $data['suggested_assignee_id'] ?? null,
                'due_date'               => $data['due_date'] ?? null,
                'confidence'             => $data['confidence'] ?? null,
                'source_excerpt'         => $data['source_excerpt'] ?? null,
                'possible_duplicate_of'  => $data['possible_duplicate_of'] ?? null,
                'status'                 => ProjectIntakeProposal::STATUS_PENDING,
            ]);
            $proposalCount++;
        }

        $intake->update([
            'status'                    => ProjectIntake::STATUS_READY,
            'provider'                  => $result['provider'] ?? config('ai.provider', 'ollama'),
            'model'                     => $result['model'] ?? config('ollama.model'),
            'ollama_summary'            => $result['summary'] ?? null,
            'processing_completed_at'   => now(),
        ]);

        Log::info('Hacklog AI: intake job completed', [
            'intake_id'      => $intake->id,
            'correlation_id' => $intake->correlation_id,
            'proposal_count' => $proposalCount,
        ]);

        $this->notifySlack($intake, $proposalCount, failed: false, botService: $botService);
    }

    /**
     * Called by Laravel when the job throws an uncaught exception.
     * Marks the intake as failed so the UI surfaces the error.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Hacklog AI: intake job threw exception', [
            'intake_id'       => $this->intakeId,
            'exception_class' => get_class($exception),
            'error'           => $exception->getMessage(),
        ]);

        $intake = ProjectIntake::with('project')->find($this->intakeId);

        if ($intake && !$intake->isTerminal()) {
            $intake->update([
                'status'                  => ProjectIntake::STATUS_FAILED,
                'error_message'           => 'Job failed: ' . $exception->getMessage(),
                'processing_completed_at' => now(),
            ]);

            $this->notifySlack($intake, 0, failed: true, botService: app(SlackBotService::class));
        }
    }

    /**
     * Post a completion or failure notification back to the originating Slack thread,
     * if this intake was created from Slack.
     *
     * Slack failure is logged but never throws — Hacklog persistence is authoritative.
     */
    private function notifySlack(
        ProjectIntake $intake,
        int           $proposalCount,
        bool          $failed,
        SlackBotService $botService
    ): void {
        if ($intake->source_type !== ProjectIntake::SOURCE_TYPE_SLACK) {
            return;
        }

        $slackContext = $intake->slack_context;
        if (!is_array($slackContext) || empty($slackContext['channel_id'])) {
            return;
        }

        $channelId = (string) $slackContext['channel_id'];
        $threadTs  = isset($slackContext['thread_ts']) ? (string) $slackContext['thread_ts'] : null;

        try {
            $intakeUrl = route('projects.intake.show', [$intake->project, $intake]);

            if ($failed) {
                $message = "I couldn't finish analyzing this thread. The intake is saved in Hacklog: {$intakeUrl}";
            } elseif ($proposalCount > 0) {
                $noun    = $proposalCount === 1 ? 'proposed task' : 'proposed tasks';
                $message = "Done — I found *{$proposalCount} {$noun}* for *{$intake->project->name}*. Review them in Hacklog: {$intakeUrl}";
            } else {
                $message = "Done — I didn't find any clear actionable tasks in this thread. The intake is saved in Hacklog: {$intakeUrl}";
            }

            $botService->postMessage($channelId, $message, $threadTs);

            Log::info('Hacklog AI: intake Slack completion notification posted.', [
                'intake_id'  => $intake->id,
                'channel_id' => $channelId,
                'failed'     => $failed,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Hacklog AI: intake Slack completion notification failed.', [
                'intake_id'       => $intake->id,
                'exception_class' => get_class($exception),
                'error'           => $exception->getMessage(),
            ]);
        }
    }
}
