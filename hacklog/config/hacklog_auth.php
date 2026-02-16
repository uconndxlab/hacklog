<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Driver
    |--------------------------------------------------------------------------
    |
    | This determines how users authenticate with the application.
    |
    | Supported: "local", "cas"
    |
    | - local: Standard email + password authentication
    | - cas: Central Authentication Service (NetID-based)
    |
    */
    'driver' => env('AUTH_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Create Users (CAS mode only)
    |--------------------------------------------------------------------------
    |
    | When true, users who successfully authenticate via CAS will be
    | automatically created in the local database if they don't exist.
    |
    */
    'cas_auto_create_users' => env('CAS_AUTO_CREATE_USERS', false),

    /*
    |--------------------------------------------------------------------------
    | Default Role for Auto-Created Users
    |--------------------------------------------------------------------------
    |
    | When CAS users are auto-created, they will be assigned this role.
    |
    | Supported: "team", "client"
    |
    */
    'cas_default_role' => env('CAS_DEFAULT_ROLE', 'team'),
];
