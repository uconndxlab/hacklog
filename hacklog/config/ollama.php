<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ollama Base URL
    |--------------------------------------------------------------------------
    |
    | URL for your local Ollama HTTP server.
    |
    */
    'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),

    /*
    |--------------------------------------------------------------------------
    | Ollama Model
    |--------------------------------------------------------------------------
    |
    | Default model name used for generate requests.
    |
    */
    'model' => env('OLLAMA_MODEL', 'gemma4'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeouts
    |--------------------------------------------------------------------------
    |
    | Request timeout and connect timeout (seconds) for Ollama calls.
    |
    */
    'timeout_seconds' => (int) env('OLLAMA_TIMEOUT_SECONDS', 90),
    'connect_timeout_seconds' => (int) env('OLLAMA_CONNECT_TIMEOUT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Ollama Generate Endpoint
    |--------------------------------------------------------------------------
    |
    | Relative endpoint path used for prompt generation.
    |
    */
    'generate_path' => env('OLLAMA_GENERATE_PATH', '/api/generate'),

    /*
    |--------------------------------------------------------------------------
    | Ollama Chat Endpoint
    |--------------------------------------------------------------------------
    |
    | Relative endpoint path used for chat + tool-calling interactions.
    |
    */
    'chat_path' => env('OLLAMA_CHAT_PATH', '/api/chat'),
];
