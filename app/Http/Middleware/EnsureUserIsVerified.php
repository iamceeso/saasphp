<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    private const EMAIL_SENT_SESSION_KEY = 'verification.email_prompt_sent';
    private const PHONE_SENT_SESSION_KEY = 'verification.phone_prompt_sent';

    /**
     * Handle an incoming request.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $emailVerified = $user->hasVerifiedEmail();
        $phoneVerified = $user->hasVerifiedPhone();

        if ($emailVerified && $phoneVerified) {
            $request->session()->forget([
                self::EMAIL_SENT_SESSION_KEY,
                self::PHONE_SENT_SESSION_KEY,
            ]);

            return $next($request);
        }

        if ($user->email && ! $emailVerified) {
            $this->sendVerificationEmailOnce($request, $user);
        }

        if ($user->phone && ! $phoneVerified) {
            $this->sendPhoneVerificationOnce($request, $user);
        }

        if ($user->phone && ! $phoneVerified) {
            return Inertia::render('auth/verify-phone');
        }

        if ($user->email && ! $emailVerified) {
            return Inertia::render('auth/verify-email');
        }

        return $next($request);
    }

    private function sendVerificationEmailOnce(Request $request, $user): void
    {
        if ($request->session()->has(self::EMAIL_SENT_SESSION_KEY)) {
            return;
        }

        $user->sendVerificationEmailWithRateLimit();
        $request->session()->put(self::EMAIL_SENT_SESSION_KEY, true);
    }

    private function sendPhoneVerificationOnce(Request $request, $user): void
    {
        if ($request->session()->has(self::PHONE_SENT_SESSION_KEY)) {
            return;
        }

        $user->sendPhoneVerificationCodeWithRateLimit();
        $request->session()->put(self::PHONE_SENT_SESSION_KEY, true);
    }
}
