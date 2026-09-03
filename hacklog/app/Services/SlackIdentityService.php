<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Parses and links a verified Slack sender to an existing Hacklog user.
 */
class SlackIdentityService
{
    public const LINKED = 'linked';

    public const ALREADY_LINKED = 'already_linked';

    public const USER_NOT_FOUND = 'user_not_found';

    public const SLACK_ALREADY_LINKED = 'slack_already_linked';

    public const USER_ALREADY_LINKED = 'user_already_linked';

    /**
     * Extract a NetID from an identity command, or null for any other message.
     *
     * Accepted examples: "I am jmk22028" and "I'm jmk22028".
     */
    public function netidFromCommand(string $message): ?string
    {
        if (! preg_match('/^(?:i\s+am|i[\'\x{2019}]m)\s+([a-z0-9][a-z0-9._-]{0,254}?)[.!]?$/iu', trim($message), $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * Create a one-to-one link without allowing either side to take over an
     * existing link.
     *
     * @return array{status: string, user: User|null}
     */
    public function link(string $slackId, string $netid): array
    {
        return DB::transaction(function () use ($slackId, $netid): array {
            $user = User::query()
                ->whereRaw('LOWER(netid) = ?', [strtolower($netid)])
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return ['status' => self::USER_NOT_FOUND, 'user' => null];
            }

            $slackUser = User::query()
                ->where('slack_id', $slackId)
                ->lockForUpdate()
                ->first();

            if ($slackUser?->is($user)) {
                return ['status' => self::ALREADY_LINKED, 'user' => $user];
            }

            if ($slackUser) {
                return ['status' => self::SLACK_ALREADY_LINKED, 'user' => null];
            }

            if ($user->slack_id !== null) {
                return ['status' => self::USER_ALREADY_LINKED, 'user' => null];
            }

            $user->update(['slack_id' => $slackId]);

            return ['status' => self::LINKED, 'user' => $user];
        });
    }

    /**
     * Slack user IDs mentioned in message text, in order of appearance.
     *
     * @return string[]
     */
    public function slackIdsFromMentions(string $text): array
    {
        preg_match_all('/<@([A-Z0-9]+)(?:\|[^>]+)?>/i', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Slack user IDs mentioned in the message, excluding the sender.
     *
     * @return string[]
     */
    public function otherMentionedSlackIds(string $text, string $senderSlackId): array
    {
        return array_values(array_filter(
            $this->slackIdsFromMentions($text),
            fn (string $id): bool => strcasecmp($id, $senderSlackId) !== 0
        ));
    }

    public function linkedUserBySlackId(string $slackId): ?User
    {
        return User::query()
            ->where('slack_id', $slackId)
            ->where('active', true)
            ->first();
    }

    /**
     * First mentioned Slack ID that is linked to an active Hacklog user.
     *
     * @param  string[]  $slackIds
     */
    public function firstLinkedUser(array $slackIds): ?User
    {
        foreach ($slackIds as $slackId) {
            $user = $this->linkedUserBySlackId($slackId);
            if ($user) {
                return $user;
            }
        }

        return null;
    }
}
