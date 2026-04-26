<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('maintenance mode blocks regular users from the dashboard', function () {
    Setting::create([
        'key' => 'features.maintenance_mode',
        'value' => 'true',
        'type' => 'boolean',
        'group' => 'features',
    ]);

    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertStatus(503);
});
