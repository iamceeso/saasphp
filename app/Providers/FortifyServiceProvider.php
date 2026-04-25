<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        /**
         * Fortify Routes
         */
        Fortify::twoFactorChallengeView(function () {
            return Inertia::render('auth/two-factor-challenge');
        });

        Fortify::loginView(function (Request $request) {
            $twoFactorEnabled = Setting::getBooleanValue('features.enable_two_factor_auth', 'false');

            return Inertia::render('auth/login', [
                'twoFactorEnabled' => $twoFactorEnabled,
                'canResetPassword' => Route::has('password.request'),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::registerView(function () {
            $registrationEnabled = Setting::getBooleanValue('features.enable_registration', 'false');
            $twoFactorEnabled = Setting::getBooleanValue('features.enable_two_factor_auth', 'false');

            if (! $registrationEnabled) {
                return Inertia::render('auth/login');
            }

            return Inertia::render('auth/register', ['twoFactorEnabled' => $twoFactorEnabled]);
        });

        Fortify::requestPasswordResetLinkView(function () {
            return Inertia::render('auth/forgot-password');
        });

        Fortify::verifyEmailView(function () {
            if (
                ! Setting::getBooleanValue('features.enable_email_verification', 'false')
            ) {
                return redirect('/');
            }

            return Inertia::render('auth/verify-email');
        });

        // Fortify::resetPasswordView(function () {
        //     return Inertia::render('auth/reset-password');
        // });

        Fortify::confirmPasswordView(function () {
            return Inertia::render('auth/confirm-password');
        });

        Fortify::authenticateUsing(function (Request $request) {

            $request->validate([
                'login' => 'required|string',
                'password' => 'required|string',
            ]);

            $login = $request->input('login');
            $password = $request->input('password');

            $user = User::where('email', $login)
                ->orWhere('phone', $login)
                ->first();

            if ($user && Hash::check($password, $user->password)) {
                return $user;
            }

            return null;
        });

    }
}
