<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use STS\FilamentImpersonate\ImpersonateManager;

uses(RefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile settings are blocked while impersonating via local table action session', function () {
    $user = User::factory()->create();
    $impersonator = User::factory()->create();

    $this
        ->actingAs($user)
        ->withSession(['impersonator_id' => $impersonator->id])
        ->get('/settings/profile')
        ->assertForbidden();
});

test('profile settings are blocked while impersonating via vendor page action session', function () {
    $user = User::factory()->create();
    $impersonator = User::factory()->create();

    $this
        ->actingAs($user)
        ->withSession([ImpersonateManager::SESSION_KEY => $impersonator->id])
        ->get('/settings/profile')
        ->assertForbidden();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@saasphp.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@saasphp.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can soft-delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();

    // Laravel’s built-in assertion for soft deletes:
    $this->assertSoftDeleted('users', [
        'id' => $user->id,
    ]);

    // or, if you prefer to check via the model instance
    $trashed = User::withTrashed()->find($user->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->trashed())->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});
