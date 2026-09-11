<?php

namespace App\Console\Commands;

use App\Services\HoneycrispBilledTotalsSyncer;
use Illuminate\Console\Command;

class SyncHoneycrispBilledTotals extends Command
{
    protected $signature = 'honeycrisp:sync-billed-totals {--force : Refetch every linked project, ignoring cache TTL}';

    protected $description = 'Refresh cached Honeycrisp billed totals for linked projects';

    public function handle(HoneycrispBilledTotalsSyncer $syncer): int
    {
        $count = $syncer->refreshStale(null, (bool) $this->option('force'));

        $this->info("Refreshed billed totals for {$count} project(s).");

        return self::SUCCESS;
    }
}
