<?php

use App\Http\Controllers\LocalAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Local Authentication Routes
|--------------------------------------------------------------------------
|
| These routes are loaded when AUTH_DRIVER=local is set in the .env file.
| They provide standard email/password authentication.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LocalAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LocalAuthController::class, 'login']);
    
    Route::get('/forgot-password', [LocalAuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [LocalAuthController::class, 'sendResetLink'])->name('password.email');
    
    Route::get('/reset-password/{token}', [LocalAuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [LocalAuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [LocalAuthController::class, 'logout'])->name('logout')->middleware('auth');
