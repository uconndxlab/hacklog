<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CAS Authentication Routes
|--------------------------------------------------------------------------
|
| These routes are loaded when AUTH_DRIVER=cas is set in the .env file.
| They provide Central Authentication Service (NetID) authentication.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::get('/login/cas', [AuthController::class, 'login'])->name('login.cas');
    Route::get('/login/cas/callback', [AuthController::class, 'handleCasCallback'])->name('login.cas.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
