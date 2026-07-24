<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles password reset completion for recovery links.
 */
class NewPasswordController extends Controller
{
    /**
     * Show the password reset page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $login = $request->email;

        if (str_starts_with($login, 'phone:')) {
            $phone = str_replace('phone:', '', $login);
            $user = User::where('phone', $phone)->first();
            $tokenOwner = DB::table('password_reset_tokens')->where('email', $login)->first();
        } else {
            $user = User::where('email', $login)->first();
            $tokenOwner = DB::table('password_reset_tokens')->where('email', $login)->first();
        }

        if (
            ! $user
            || ! $tokenOwner
            || $this->passwordResetTokenExpired($tokenOwner->created_at)
            || ! Hash::check($request->token, $tokenOwner->token)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Invalid user or expired reset token.'],
            ]);
        }

        //  reset the user's password.
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        if (str_starts_with($login, 'phone:')) {
            $user->markPhoneAsVerified();
        } else {
            $user->markEmailAsVerified();
        }

        event(new PasswordReset($user));

        // Clean up used token
        Password::deleteToken($user);

        return to_route('login')->with('status', 'Password reset successful.');
    }

    private function passwordResetTokenExpired(mixed $createdAt): bool
    {
        if (blank($createdAt)) {
            return true;
        }

        $broker = config('auth.defaults.passwords', 'users');
        $expiresInMinutes = (int) config("auth.passwords.{$broker}.expire", 60);

        return Carbon::parse($createdAt)->addMinutes($expiresInMinutes)->isPast();
    }
}
