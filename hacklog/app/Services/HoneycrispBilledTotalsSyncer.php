<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Refresh cached Honeycrisp billed totals on linked projects.
 */
class HoneycrispBilledTotalsSyncer
{
    public function __construct(private HoneycrispClient $client) {}

    /**
     * Sync linked projects. Pass a collection to limit to those rows.
     * When $force is true, every linked project is refetched regardless of TTL.
     *
     * @param  Collection<int, Project>|null  $projects
     * @return int Number of projects refreshed
     */
    public function refreshStale(?Collection $projects = null, bool $force = false): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        $stale = $this->staleProjects($projects, $force);

        if ($stale->isEmpty()) {
            return 0;
        }

        $refreshed = 0;

        foreach ($stale as $project) {
            $result = $this->client->getProjectTotal((int) $project->honeycrisp_project_id);

            if (! $result['ok']) {
                continue;
            }

            $project->forceFill([
                'honeycrisp_billed_total_cents' => $result['total'],
                'honeycrisp_billed_fetched_at' => now(),
            ])->save();

            $refreshed++;
        }

        return $refreshed;
    }

    /**
     * @param  Collection<int, Project>|null  $projects
     * @return Collection<int, Project>
     */
    protected function staleProjects(?Collection $projects, bool $force = false): Collection
    {
        $ttlSeconds = max(0, (int) config('honeycrisp.billed_cache_seconds', 3600));
        $staleBefore = Carbon::now()->subSeconds($ttlSeconds);

        if ($projects !== null) {
            return $projects
                ->filter(fn (Project $project) => $project->honeycrisp_project_id !== null)
                ->filter(function (Project $project) use ($staleBefore, $ttlSeconds, $force) {
                    if ($force) {
                        return true;
                    }

                    if ($project->honeycrisp_billed_fetched_at === null) {
                        return true;
                    }

                    if ($ttlSeconds === 0) {
                        return true;
                    }

                    return $project->honeycrisp_billed_fetched_at->lte($staleBefore);
                })
                ->values();
        }

        $query = Project::query()->whereNotNull('honeycrisp_project_id');

        if (! $force) {
            $query->where(function ($query) use ($staleBefore, $ttlSeconds) {
                $query->whereNull('honeycrisp_billed_fetched_at');

                if ($ttlSeconds === 0) {
                    $query->orWhereNotNull('honeycrisp_billed_fetched_at');
                } else {
                    $query->orWhere('honeycrisp_billed_fetched_at', '<=', $staleBefore);
                }
            });
        }

        return $query->orderBy('id')->get();
    }
}