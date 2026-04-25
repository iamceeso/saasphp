<?php

use App\Http\Middleware\EnsureUserIsVerified;
use App\Http\Middleware\MaintenanceModeEnabled;
use App\Http\Middleware\PreventAdminAccessToUserArea;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    // Dashboard (requires extra checks)
    Route::middleware([
        EnsureUserIsVerified::class,
        MaintenanceModeEnabled::class,
        PreventAdminAccessToUserArea::class,
    ])->group(function () {
        Route::get('dashboard', function () {
            return Inertia::render('dashboard');
        })->name('dashboard');
    });

    // Impersonation “leave” (only needs auth)
    Route::get('impersonate/leave', function () {
        if (Session::has('impersonator_id')) {
            Auth::loginUsingId(Session::pull('impersonator_id'));
        }

        return redirect('/admin');
    })->name('impersonate.leave');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

if (config('billing.enabled')) {
    require __DIR__.'/billing.php';
}
