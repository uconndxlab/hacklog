<?php

namespace App\Jobs;

use App\Models\ProjectIntake;
use App\Models\ProjectIntakeProposal;
use App\Services\ProjectIntakeService;
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

    public function handle(ProjectIntakeService $service): void
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
            'model'                     => config('ollama.model'),
            'ollama_summary'            => $result['summary'] ?? null,
            'processing_completed_at'   => now(),
        ]);

        Log::info('Hacklog AI: intake job completed', [
            'intake_id'      => $intake->id,
            'correlation_id' => $intake->correlation_id,
            'proposal_count' => $proposalCount,
        ]);
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

        $intake = ProjectIntake::find($this->intakeId);

        if ($intake && !$intake->isTerminal()) {
            $intake->update([
                'status'                  => ProjectIntake::STATUS_FAILED,
                'error_message'           => 'Job failed: ' . $exception->getMessage(),
                'processing_completed_at' => now(),
            ]);
        }
    }
}
