<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoadEmailConfig;
use App\Services\LoadSmsConfig;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    use LoadEmailConfig, LoadSmsConfig;

    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->login)
            ->orWhere('phone', $request->login)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => __('No account found for that email or phone number.'),
            ]);
        }

        // Generate a new token
        $token = Str::random(64);

        // Store the token in the database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email ?? "phone:{$user->phone}"], // distinguish phone from email
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Create the reset URL
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email ? $user->email : "phone:{$user->phone}",
        ], false));

        // Send email if email is available
        if ($user->email) {
            $this->loadEmailConfig();
            $user->notify(new ResetPassword($token));
            $user->markEmailAsVerified();
        }
        // Or send SMS if only phone exists
        elseif ($user->phone) {
            $this->loadDynamicSmsConfig(message: "Reset your password: {$resetUrl}", phone: $user->phone);
            $user->markPhoneAsVerified();
        }

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
