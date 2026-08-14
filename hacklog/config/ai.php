<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Which AI provider to use for the Hacklog AI Intake workflow.
    | Supported: "ollama", "openai"
    |
    | ollama  — local Ollama server (see config/ollama.php for Ollama settings)
    | openai  — institution OpenAI API (configure keys below)
    |
    */
    'provider' => env('AI_PROVIDER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Settings
    |--------------------------------------------------------------------------
    |
    | Used when AI_PROVIDER=openai.
    | Do not commit API keys — always use environment variables.
    |
    */
    'openai' => [
        'api_key'         => env('OPENAI_API_KEY', ''),
        'model'           => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url'        => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 60),
    ],
];
