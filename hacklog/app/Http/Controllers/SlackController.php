<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSlackEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming Slack Events API requests.
 *
 * Responsibilities:
 *   1. Verify Slack request signature (HMAC-SHA256)
 *   2. Respond to the URL verification challenge
 *   3. Deduplicate retried events
 *   4. Dispatch verified app_mention events to the queue
 *   5. Return 200 immediately so Slack doesn't retry
 *
 * The controller does NO database work and NO outgoing HTTP.
 * All actual processing (channel lookup, query, reply) happens in ProcessSlackEventJob.
 */
class SlackController extends Controller
{
    /**
     * POST /api/slack/events
     */
    public function events(Request $request): JsonResponse
    {
        // --- Step 1: Verify Slack signature ---
        $this->verifySlackSignature($request);

        $payload = $request->json()->all();
        $type    = (string) ($payload['type'] ?? '');

        Log::info('Slack bot: event received.', [
            'type'     => $type,
            'event_id' => $payload['event_id'] ?? null,
        ]);

        // --- Step 2: URL verification challenge (one-time during app setup) ---
        if ($type === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        // --- Step 3: Only handle event_callback ---
        if ($type !== 'event_callback') {
            return response()->json(['ok' => true]);
        }

        $eventId   = (string) ($payload['event_id'] ?? '');
        $eventType = (string) ($payload['event']['type'] ?? '');

        // --- Step 4: Deduplicate (Slack retries unacknowledged events) ---
        if ($eventId !== '' && !Cache::add("slack_event:{$eventId}", 1, now()->addHours(24))) {
            Log::info('Slack bot: duplicate event ignored.', ['event_id' => $eventId]);
            return response()->json(['ok' => true]);
        }

        // --- Step 5: Only dispatch app_mention events ---
        if ($eventType !== 'app_mention') {
            return response()->json(['ok' => true]);
        }

        // Ignore self-generated events immediately (prevent response loops)
        $event = $payload['event'] ?? [];
        if (!empty($event['bot_id'])) {
            return response()->json(['ok' => true]);
        }

        ProcessSlackEventJob::dispatch($payload, $eventId);

        Log::info('Slack bot: app_mention dispatched to queue.', [
            'event_id'   => $eventId,
            'channel_id' => $event['channel'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Signature verification
    // -------------------------------------------------------------------------

    /**
     * Verify the Slack request signature using HMAC-SHA256.
     *
     * Slack documentation: https://api.slack.com/authentication/verifying-requests-from-slack
     * Aborts with 403 on failure so Slack receives an error and can alert operators.
     */
    protected function verifySlackSignature(Request $request): void
    {
        $signingSecret = (string) config('slack.signing_secret', '');

        if ($signingSecret === '') {
            Log::error('Slack bot: SLACK_SIGNING_SECRET is not configured.');
            abort(403, 'Slack integration is not configured.');
        }

        $timestamp = (string) $request->header('X-Slack-Request-Timestamp', '');
        $signature = (string) $request->header('X-Slack-Signature', '');

        if ($timestamp === '' || $signature === '') {
            Log::warning('Slack bot: request missing signature headers.');
            abort(403, 'Missing Slack signature headers.');
        }

        // Reject requests older than 5 minutes (replay-attack protection)
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Slack bot: stale request rejected.', ['timestamp' => $timestamp]);
            abort(403, 'Stale Slack request.');
        }

        $rawBody       = $request->getContent();
        $baseString    = "v0:{$timestamp}:{$rawBody}";
        $expected      = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('Slack bot: invalid signature — request rejected.');
            abort(403, 'Invalid Slack request signature.');
        }
    }
}
