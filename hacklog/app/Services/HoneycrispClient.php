<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Honeycrisp API client.
 */
class HoneycrispClient
{
    /**
     * @return list<array{id: int|string, name: string}>
     */
    public function listFacilityProjects(): array
    {
        $facilityId = (string) config('honeycrisp.facility_id', '');

        if ($facilityId === '' || ! $this->isConfigured()) {
            return [];
        }

        $cacheKey = 'honeycrisp.facility_projects.'.$facilityId;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->get('/facilities/'.$facilityId.'/projects');

        if ($response === null) {
            return [];
        }

        if (! $response->successful()) {
            Log::warning('Honeycrisp: facility projects request failed.', [
                'status' => $response->status(),
                'facility_id' => $facilityId,
            ]);

            return [];
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            Log::warning('Honeycrisp: facility projects response missing data array.', [
                'facility_id' => $facilityId,
            ]);

            return [];
        }

        $projects = [];

        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['name'])) {
                continue;
            }

            $projects[] = [
                'id' => $row['id'],
                'name' => (string) $row['name'],
            ];
        }

        // Cache successful responses so create/edit form load + submit share one fetch.
        Cache::put($cacheKey, $projects, 60);

        return $projects;
    }

    /**
     * Fetch billed order total for a Honeycrisp project (cents).
     *
     * @return array{ok: bool, total: int|null}
     */
    public function getProjectTotal(int $honeycrispProjectId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'total' => null];
        }

        $response = $this->get('/projects/'.$honeycrispProjectId.'/total');

        if ($response === null) {
            return ['ok' => false, 'total' => null];
        }

        if ($response->status() === 404) {
            return ['ok' => true, 'total' => null];
        }

        if (! $response->successful()) {
            Log::warning('Honeycrisp: project total request failed.', [
                'status' => $response->status(),
                'honeycrisp_project_id' => $honeycrispProjectId,
            ]);

            return ['ok' => false, 'total' => null];
        }

        $total = $response->json('data.total');

        if (! is_numeric($total)) {
            Log::warning('Honeycrisp: project total response missing data.total.', [
                'honeycrisp_project_id' => $honeycrispProjectId,
            ]);

            return ['ok' => false, 'total' => null];
        }

        return ['ok' => true, 'total' => (int) $total];
    }

    public function isConfigured(): bool
    {
        $baseUrl = rtrim((string) config('honeycrisp.base_url', ''), '/');
        $token = (string) config('honeycrisp.token', '');

        return $baseUrl !== '' && $token !== '';
    }

    protected function get(string $path): ?Response
    {
        $baseUrl = rtrim((string) config('honeycrisp.base_url', ''), '/');
        $token = (string) config('honeycrisp.token', '');
        $url = $baseUrl.'/'.ltrim($path, '/');

        try {
            return Http::withToken($token)
                ->timeout((int) config('honeycrisp.timeout_seconds', 10))
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $exception) {
            Log::warning('Honeycrisp: request exception.', [
                'url' => $url,
                'exception_class' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
