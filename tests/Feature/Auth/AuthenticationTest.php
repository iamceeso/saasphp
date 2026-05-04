<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    /** @var TestCase $this */
    $response = $this->get('/login');

    $response->assertStatus(200);

    $response->assertInertia(function (Assert $page) {
        $page->component('auth/login');
    });
});

test('users can authenticate using the login screen', function () {
    /** @var TestCase $this */
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect('/');
});

test('users can not authenticate with invalid password', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
