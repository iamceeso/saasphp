<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyPhoneController;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    // Social login (with 2FA enabled)
    Route::middleware(EnsureTwoFactorEnabled::class)->group(function () {
        collect(['google', 'microsoft', 'yahoo', 'github', 'twitter'])
            ->each(fn ($provider) => [
                Route::get("login/{$provider}", [AuthenticatedSessionController::class, 'redirectTo'.ucfirst($provider)])
                    ->name("{$provider}.login"),
                Route::get("auth/{$provider}/callback", [AuthenticatedSessionController::class, 'handle'.ucfirst($provider).'Callback']),
            ]);
    });

    // Magic link
    Route::prefix('magic')->name('magic.')->group(function () {
        Route::get('/login', fn () => Inertia::render('auth/magic-login'))->name('login');
        Route::post('/send', [MagicLinkController::class, 'send'])->name('send');
        Route::get('/verify', [MagicLinkController::class, 'login'])->name('verify');
    });

    // Password reset
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class,   'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class,   'store'])->name('password.update');
});

Route::middleware('auth')->prefix('phone')->name('phone.')->group(function () {
    Route::post('send', [VerifyPhoneController::class, 'send'])->name('send');
    Route::post('verify', [VerifyPhoneController::class, 'verify'])->name('verify');
});
