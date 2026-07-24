<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration screen can be rendered', function () {
    Setting::updateOrCreate(
        ['key' => 'features.enable_registration'],
        ['value' => true, 'type' => 'boolean', 'group' => 'features']
    );

    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Setting::updateOrCreate(
        ['key' => 'features.enable_registration'],
        ['value' => true, 'type' => 'boolean', 'group' => 'features']
    );

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@saasphp.com',
        'phone' => '1234567890',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/');
});

test('new users cannot register when registration is disabled', function () {
    Setting::updateOrCreate(
        ['key' => 'features.enable_registration'],
        ['value' => false, 'type' => 'boolean', 'group' => 'features']
    );

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@saasphp.com',
        'phone' => '1234567890',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('registration');
    $this->assertDatabaseMissing('users', [
        'email' => 'test@saasphp.com',
    ]);
});
