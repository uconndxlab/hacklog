<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Slack Web API client for posting bot messages.
 *
 * Uses the bot token (SLACK_BOT_TOKEN) and chat.postMessage.
 * Separate from the project webhook mechanism; this is for interactive bot replies.
 */
class SlackBotService
{
    private const API_POST_MESSAGE = 'https://slack.com/api/chat.postMessage';

    /**
     * Post a message to a Slack channel, optionally replying in a thread.
     *
     * Replies in thread when $threadTs is provided, which covers:
     *   - top-level mentions: pass event['ts'] so the reply starts a thread
     *   - threaded mentions: pass event['thread_ts'] to continue the thread
     */
    public function postMessage(string $channelId, string $text, ?string $threadTs = null): void
    {
        $token = (string) config('slack.bot_token', '');

        if ($token === '') {
            Log::warning('Slack bot: SLACK_BOT_TOKEN is not configured; cannot post message.', [
                'channel_id' => $channelId,
            ]);
            return;
        }

        $payload = [
            'channel'  => $channelId,
            'text'     => $text,
            'mrkdwn'   => true,
        ];

        if ($threadTs !== null) {
            $payload['thread_ts'] = $threadTs;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post(self::API_POST_MESSAGE, $payload);

            $ok = $response->json('ok');

            if (!$ok) {
                Log::warning('Slack bot: chat.postMessage returned ok=false.', [
                    'channel_id' => $channelId,
                    'error'      => $response->json('error'),
                ]);
            } else {
                Log::info('Slack bot: message posted.', [
                    'channel_id' => $channelId,
                    'ts'         => $response->json('ts'),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Slack bot: chat.postMessage exception.', [
                'channel_id'      => $channelId,
                'exception_class' => get_class($exception),
                'error'           => $exception->getMessage(),
            ]);
        }
    }
}
