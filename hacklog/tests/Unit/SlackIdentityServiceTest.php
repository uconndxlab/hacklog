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

    public function test_netid_is_extracted_only_from_an_identity_command(): void
    {
        $service = new SlackIdentityService;

        $this->assertSame('jmk22028', $service->netidFromCommand('I am JMK22028.'));
        $this->assertSame('jmk22028', $service->netidFromCommand("I'm jmk22028"));
        $this->assertNull($service->netidFromCommand('show tasks for jmk22028'));
        $this->assertNull($service->netidFromCommand('I am jmk22028 and show my tasks'));
    }

    public function test_labeled_user_mentions_are_parsed_and_stripped(): void
    {
        $service = new SlackIdentityService;
        $text = '<@UBOT123|Hacklog> what are <@UJAY|Jay Kay>\'s tasks';

        $this->assertSame(
            ['UBOT123', 'UJAY'],
            $service->slackIdsFromMentions($text)
        );
        $this->assertSame(
            ['UJAY'],
            $service->otherMentionedSlackIds($text, 'UASKER', 'UBOT123')
        );
        $this->assertSame(
            "what are 's tasks",
            trim($service->stripUserMentions($text))
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
