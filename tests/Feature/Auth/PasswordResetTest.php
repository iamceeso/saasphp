<?php

use App\Models\User;
use App\Models\Setting;
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
