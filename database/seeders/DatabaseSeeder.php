<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (config('saasphp-data', []) as $group => $settings) {
            foreach ($settings as $key => $meta) {
                Setting::firstOrCreate(
                    ['key' => $key],
                    [
                        'value' => $meta['type'] === 'boolean'
                            ? ($meta['value'] ? 'true' : 'false')
                            : (string) $meta['value'],
                        'type' => $meta['type'],
                        'group' => $group,
                    ]
                );
            }
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $userRole = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@saasphp.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        $adminUser->syncRoles([$adminRole]);

        $userOne = User::firstOrCreate(
            ['email' => 'user1@saasphp.com'],
            [
                'name' => 'User One',
                'password' => 'password',
            ]
        );
        $userOne->syncRoles([$userRole]);

        $userTwo = User::firstOrCreate(
            ['email' => 'user2@saasphp.com'],
            [
                'name' => 'User Two',
                'password' => 'password',
            ]
        );
        $userTwo->syncRoles([$userRole]);
    }
}
