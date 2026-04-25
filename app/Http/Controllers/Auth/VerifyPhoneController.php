<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class VerifyPhoneController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user || !$user->phone) {
            return back()->withErrors(['code' => 'No phone number available.']);
        }

        if (!$user->sendPhoneVerificationCodeWithRateLimit()) {
            return back()->withErrors(['code' => 'Too many attempts. Try again later.']);
        }

        return back()->with('status', 'verification-link-sent');
    }


    public function verify(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $codeInput = $request->input('code');

        // Find latest unused, unexpired code
        $record = PhoneCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$record || !Hash::check($codeInput, $record->code)) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        // Mark the code as used
        $record->update(['used_at' => now()]);

        // Mark the user as verified
        $user->markPhoneAsVerified();

        return redirect()->intended('dashboard')->with('status', 'Phone number verified!');
    }
}
