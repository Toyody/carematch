<?php

use App\Modules\Identity\Interfaces\Http\Controllers\ForgotPasswordController;
use App\Modules\Identity\Interfaces\Http\Controllers\LoginController;
use App\Modules\Identity\Interfaces\Http\Controllers\LogoutController;
use App\Modules\Identity\Interfaces\Http\Controllers\MeController;
use App\Modules\Identity\Interfaces\Http\Controllers\RegisterController;
use App\Modules\Identity\Interfaces\Http\Controllers\ResetPasswordController;
use App\Modules\Identity\Interfaces\Http\Middleware\EnsureGuestApi;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
    Route::middleware(EnsureGuestApi::class)->group(function (): void {
        Route::post('/register', RegisterController::class)
            ->middleware(ThrottleRequests::using('identity-registration'))
            ->name('register');
        Route::post('/login', LoginController::class)->name('login');
        Route::post('/forgot-password', ForgotPasswordController::class)
            ->middleware(ThrottleRequests::using('identity-forgot-password'))
            ->name('forgot-password');
        Route::post('/reset-password', ResetPasswordController::class)
            ->middleware(ThrottleRequests::using('identity-reset-password'))
            ->name('reset-password');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', MeController::class)->name('me');
        Route::post('/logout', LogoutController::class)->name('logout');
    });
});
