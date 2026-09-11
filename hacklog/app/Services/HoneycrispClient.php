<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Honeycrisp API client for listing facility projects.
 */
class HoneycrispClient
{
    /**
     * @return list<array{id: int|string, name: string}>
     */
    public function listFacilityProjects(): array
    {
        $baseUrl = rtrim((string) config('honeycrisp.base_url', ''), '/');
        $token = (string) config('honeycrisp.token', '');
        $facilityId = (string) config('honeycrisp.facility_id', '');

        if ($baseUrl === '' || $token === '' || $facilityId === '') {
            return [];
        }

        $url = $baseUrl.'/facilities/'.$facilityId.'/projects';

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('honeycrisp.timeout_seconds', 10))
                ->acceptJson()
                ->get($url);

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

            return $projects;
        } catch (\Throwable $exception) {
            Log::warning('Honeycrisp: facility projects exception.', [
                'facility_id' => $facilityId,
                'exception_class' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
