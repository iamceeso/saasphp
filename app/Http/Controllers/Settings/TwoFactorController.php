<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    /**
     * Show the user's two-factor authentication settings page.
     */
    public function edit(Request $request): Response
    {
        $twoFactorConfirmed = $request->user()->two_factor_confirmed_at !== null;
        $shouldExposeSetupState = $request->user()->two_factor_secret && ! $twoFactorConfirmed;

        return Inertia::render('settings/two-factor-authentication', [
            'twoFactorSecret' => $shouldExposeSetupState ? 'pending-setup' : null,
            'twoFactorQRCode' => $shouldExposeSetupState ? $request->user()->twoFactorQrCodeSvg() : '',
            'twoFactorRecoveryCodes' => $shouldExposeSetupState ? $request->user()->recoveryCodes() : [],
            'twoFactorConfirmation' => $twoFactorConfirmed,
        ]);
    }

    /**
     * Confirm the user's pending two-factor setup.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return back()->withErrors([
                'code' => 'Two-factor authentication has not been enabled yet.',
            ]);
        }

        if ($user->two_factor_confirmed_at) {
            return back()->with('status', 'two-factor-authentication-confirmed');
        }

        $isValid = app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($user->two_factor_secret),
            $request->string('code')->value()
        );

        if (! $isValid) {
            return back()->withErrors([
                'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return back()->with('status', 'two-factor-authentication-confirmed');
    }
}
