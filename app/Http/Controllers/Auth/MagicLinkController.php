<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class MagicLinkController.
 */
class MagicLinkController extends Controller
{
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email', '').'|'.$request->ip());
    }

    public function send(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $key = 'magic-link-send:'.$this->throttleKey($request);

        if (app(RateLimiter::class)->tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => __('auth.throttle', [
                    'seconds' => app(RateLimiter::class)->availableIn($key),
                    'minutes' => ceil(app(RateLimiter::class)->availableIn($key) / 60),
                ]),
            ]);
        }

        app(RateLimiter::class)->hit($key, 300);

        $user = User::where('email', $request->string('email')->lower()->value())->first();

        if (! $user) {
            return back()->with('status', 'If your email address exists in our system, a login link has been sent.');
        }

        // Generate raw code and expiration
        $rawCode = Str::random(40);
        $hashedCode = Hash::make($rawCode);
        $expiresAt = now()->addMinutes(5);

        // Save hashed code
        MagicLink::create([
            'user_id' => $user->id,
            'code' => $hashedCode,
            'expires_at' => $expiresAt,
        ]);

        // Build login URL
        $url = route('magic.verify', ['code' => $rawCode, 'email' => $user->email]);

        // Send email
        Mail::raw("Click here to login: $url", function ($message) use ($user) {
            $message->to($user->email)
                ->subject(Setting::getValue('site.name').' Magic Login Link');
        });

        return back()->with('status', 'If your email address exists in our system, a login link has been sent.');
    }

    /**
     * Render a confirmation page for the emailed link. This step is a plain GET
     * with no side effects (it never touches the database or logs anyone in) so
     * that link prefetchers, email security scanners, or browser preloading
     * can't silently burn the one-time code before the real user clicks it.
     */
    public function verify(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        return Inertia::render('auth/magic-confirm', [
            'email' => $request->string('email')->value(),
            'code' => $request->string('code')->value(),
        ]);
    }

    /**
     * Actually consume the code and log the user in. Only reachable via POST,
     * triggered by the user submitting the confirmation page.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        $key = 'magic-link-login:'.$this->throttleKey($request);

        if (app(RateLimiter::class)->tooManyAttempts($key, 10)) {
            return redirect()->route('login')->withErrors([
                'code' => __('auth.throttle', [
                    'seconds' => app(RateLimiter::class)->availableIn($key),
                    'minutes' => ceil(app(RateLimiter::class)->availableIn($key) / 60),
                ]),
            ]);
        }

        app(RateLimiter::class)->hit($key, 300);

        $user = User::where('email', $request->string('email')->lower()->value())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['code' => 'Invalid or expired magic link.']);
        }

        // Find latest unused and unexpired magic link
        $magicLink = MagicLink::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $magicLink || ! Hash::check($request->code, $magicLink->code)) {
            return redirect()->route('login')->withErrors(['code' => 'Invalid or expired magic link.']);
        }

        // Mark the magic link as used
        $magicLink->update(['used_at' => Carbon::now()]);

        // Log the user in
        Auth::login($user);

        // Verify unverified email
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        app(RateLimiter::class)->clear($key);

        return redirect()->intended(route('dashboard'));
    }
}
