<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Honeycrisp Base URL
    |--------------------------------------------------------------------------
    |
    | Root URL for Honeycrisp API routes (typically includes /api), e.g.
    | https://honeycrisp.example.com/api
    |
    */
    'base_url' => env('HONEYCRISP_BASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Honeycrisp API Token
    |--------------------------------------------------------------------------
    |
    | Shared bearer token; must match Honeycrisp services.honeycrisp.token.
    |
    */
    'token' => env('HONEYCRISP_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Facility ID
    |--------------------------------------------------------------------------
    |
    | Single Honeycrisp facility whose projects can be linked from Hacklog.
    |
    */
    'facility_id' => env('HONEYCRISP_FACILITY_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('HONEYCRISP_TIMEOUT_SECONDS', 10),
];
