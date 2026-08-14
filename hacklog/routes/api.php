<?php

use App\Http\Controllers\SlackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hacklog API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the framework with the "api" middleware group.
| They are prefixed with /api and are exempt from CSRF protection.
|
*/

Route::post('/slack/events', [SlackController::class, 'events']);
