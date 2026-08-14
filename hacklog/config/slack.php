<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Slack Bot Token
    |--------------------------------------------------------------------------
    |
    | OAuth Bot Token for the Hacklog Slack app.
    | Used for chat.postMessage replies to @hacklog mentions.
    | Obtain from https://api.slack.com/apps → OAuth & Permissions.
    |
    */
    'bot_token' => env('SLACK_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Slack Signing Secret
    |--------------------------------------------------------------------------
    |
    | Signing secret for verifying incoming Slack Events API request signatures.
    | Obtain from https://api.slack.com/apps → Basic Information.
    |
    */
    'signing_secret' => env('SLACK_SIGNING_SECRET', ''),
];
