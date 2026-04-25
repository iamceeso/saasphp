<?php

namespace App\Actions\Fortify;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $requireBoth = Setting::getBooleanValue('features.phone_email_at_registration', false);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if ($requireBoth) {
            $rules['email'] = [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ];
            $rules['phone'] = [
                'required',
                'string',
                'regex:/^\+?[0-9]{1,15}$/',
                Rule::unique(User::class, 'phone'),
            ];
        } else {
            $rules['email'] = [
                'nullable',
                'required_without:phone',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ];
            $rules['phone'] = [
                'nullable',
                'required_without:email',
                'string',
                'regex:/^\+?[0-9]{1,15}$/',
                Rule::unique(User::class, 'phone'),
            ];
        }

        $validated = Validator::make($input, $rules)->validate();

        return User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => isset($validated['phone']) ? preg_replace('/\s+/', '', $validated['phone']) : null,
            'password' => Hash::make($validated['password']),
        ]);
    }
}
