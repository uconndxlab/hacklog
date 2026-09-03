<?php

namespace Tests\Unit;

use App\Services\SlackIdentityService;
use Tests\TestCase;

class SlackIdentityServiceTest extends TestCase
{
    public function test_other_mentioned_ids_exclude_sender_and_bot(): void
    {
        $service = new SlackIdentityService;
        $text = '<@UBOT123> what are <@USTRANGER>\'s tasks';

        $this->assertSame(
            ['USTRANGER'],
            $service->otherMentionedSlackIds($text, 'UASKER', 'UBOT123')
        );
        $this->assertSame(
            ['USTRANGER'],
            $service->otherMentionedSlackIds("what are <@USTRANGER>'s tasks", 'UASKER', 'UBOT123')
        );
        $this->assertSame(
            [],
            $service->otherMentionedSlackIds('<@UBOT123> show me my tasks', 'UASKER', 'UBOT123')
        );
    }

    public function test_bot_slack_user_id_comes_from_authorizations(): void
    {
        $service = new SlackIdentityService;

        $this->assertSame('UBOT123', $service->botSlackUserIdFromPayload([
            'authorizations' => [
                ['user_id' => 'UBOT123', 'is_bot' => true],
            ],
        ]));
        $this->assertSame('UBOT123', $service->botSlackUserIdFromPayload([
            'authorizations' => [
                ['user_id' => 'UBOT123'],
            ],
        ]));
        $this->assertNull($service->botSlackUserIdFromPayload(['event' => []]));
    }
}
