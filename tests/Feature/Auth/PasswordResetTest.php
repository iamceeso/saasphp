<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password request does not reveal whether an account exists', function () {
    Notification::fake();

    $response = $this->post('/forgot-password', ['login' => 'missing@example.com']);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('A reset link will be sent if the account exists.'));

    Notification::assertNothingSent();
});

test('reset password requests are rate limited', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/forgot-password', ['login' => 'missing@example.com'])
            ->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->post('/forgot-password', ['login' => 'missing@example.com'])
        ->assertTooManyRequests();
});

test('requesting a password reset link does not verify an email address', function () {
    Notification::fake();
    Setting::updateOrCreate(
        ['key' => 'features.enable_email_verification'],
        ['value' => true, 'type' => 'boolean', 'group' => 'features']
    );

    $user = User::factory()->unverified()->create();

    $this->post('/forgot-password', ['login' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('requesting a password reset link does not verify a phone number', function () {
    Setting::updateOrCreate(
        ['key' => 'features.enable_phone_verification'],
        ['value' => true, 'type' => 'boolean', 'group' => 'features']
    );

    $user = User::factory()->create([
        'email' => null,
        'phone' => '+15555550123',
        'phone_verified_at' => null,
    ]);

    $this->post('/forgot-password', ['login' => $user->phone]);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('email is verified only after password reset token is accepted', function () {
    Notification::fake();
    Setting::updateOrCreate(
        ['key' => 'features.enable_email_verification'],
        ['value' => true, 'type' => 'boolean', 'group' => 'features']
    );

    $user = User::factory()->unverified()->create();

    $this->post('/forgot-password', ['login' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        expect($user->fresh()->email_verified_at)->toBeNull();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        expect($user->fresh()->email_verified_at)->not->toBeNull();

        return true;
    });
});

test('phone is verified only after password reset token is accepted', function () {
    Setting::updateOrCreate(
        ['key' => 'features.enable_phone_verification'],
        ['value' => true, 'type' => 'boolean', 'group' => 'features']
    );

    $token = 'valid-phone-reset-token';
    $phoneLogin = 'phone:+15555550124';
    $user = User::factory()->create([
        'email' => null,
        'phone' => '+15555550124',
        'phone_verified_at' => null,
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => $phoneLogin,
        'token' => Hash::make($token),
        'created_at' => now(),
    ]);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $phoneLogin,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('password cannot be reset with an expired email token', function () {
    $token = 'expired-email-reset-token';
    $user = User::factory()->create([
        'password' => 'old-password',
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => Hash::make($token),
        'created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 1),
    ]);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('password cannot be reset with an expired phone token', function () {
    $token = 'expired-phone-reset-token';
    $phoneLogin = 'phone:+15555550125';
    $user = User::factory()->create([
        'email' => null,
        'phone' => '+15555550125',
        'password' => 'old-password',
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => $phoneLogin,
        'token' => Hash::make($token),
        'created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 1),
    ]);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $phoneLogin,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
