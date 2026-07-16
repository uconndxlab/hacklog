<?php

namespace App\Jobs;

use App\Services\ProjectSlackNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendProjectSlackTaskSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $projectId)
    {
    }

    public function handle(ProjectSlackNotificationService $notificationService): void
    {
        $notificationService->flushProjectSummary($this->projectId);
    }
}
