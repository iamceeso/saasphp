<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class AuthenticatedSessionController extends Controller
{
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }


    /**
     * Social authentication
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        return $this->socialLoginCallback('google');
    }

    public function redirectToMicrosoft(): RedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function handleMicrosoftCallback(): RedirectResponse
    {
        return $this->socialLoginCallback('microsoft');
    }

    public function redirectToYahoo(): RedirectResponse
    {
        return Socialite::driver('yahoo')->redirect();
    }

    public function handleYahooCallback(): RedirectResponse
    {
        return $this->socialLoginCallback('yahoo');
    }

    public function redirectToGithub(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function handleGithubCallback(): RedirectResponse
    {
        return $this->socialLoginCallback('github');
    }

    public function redirectToTwitter(): RedirectResponse
    {
        return Socialite::driver('twitter')->redirect();
    }

    public function handleTwitterCallback(): RedirectResponse
    {
        return $this->socialLoginCallback('twitter');
    }


    private function socialLoginCallback(string $provider): RedirectResponse
    {
        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors([
                'login' => 'We could not complete your social login. Please try again.',
            ]);
        }

        $email = $providerUser->email;
        $providerId = (string) $providerUser->getId();

        if (blank($email) || blank($providerId)) {
            return redirect()->route('login')->withErrors([
                'login' => 'Your social account did not provide the information required to sign in.',
            ]);
        }

        if (! $this->providerEmailIsVerified($provider, $providerUser)) {
            return redirect()->route('login')->withErrors([
                'login' => 'Your social account email must be verified before you can sign in.',
            ]);
        }

        $socialUser = User::query()
            ->where('oauth_provider', $provider)
            ->where('oauth_provider_id', $providerId)
            ->first();

        if (! $socialUser) {
            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser) {
                return redirect()->route('login')->withErrors([
                    'login' => 'An account with that email already exists. Please sign in with your existing method first.',
                ]);
            }

            $socialUser = User::create([
                'email' => $email,
                'name' => $providerUser->name ?: Str::before($email, '@'),
                'password' => bcrypt(Str::random(16)),
                'email_verified_at' => now(),
            ]);

            $socialUser->forceFill([
                'oauth_provider' => $provider,
                'oauth_provider_id' => $providerId,
            ])->save();
        } else {
            $emailOwner = User::query()
                ->where('email', $email)
                ->whereKeyNot($socialUser->getKey())
                ->first();

            if ($emailOwner) {
                return redirect()->route('login')->withErrors([
                    'login' => 'That verified social email is already in use by another account.',
                ]);
            }

            $updates = [
                'name' => $providerUser->name ?: $socialUser->name,
                'email' => $email,
            ];

            if ($socialUser->email_verified_at === null) {
                $updates['email_verified_at'] = now();
            }

            $socialUser->fill($updates)->save();
        }

        Auth::login($socialUser, true);
        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function providerEmailIsVerified(string $provider, object $providerUser): bool
    {
        $rawUser = property_exists($providerUser, 'user') && is_array($providerUser->user)
            ? $providerUser->user
            : [];

        $verificationFlags = [
            data_get($rawUser, 'verified_email'),
            data_get($rawUser, 'email_verified'),
            data_get($rawUser, 'verified'),
        ];

        foreach ($verificationFlags as $flag) {
            if (filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
                return true;
            }
        }

        return false;
    }
}
