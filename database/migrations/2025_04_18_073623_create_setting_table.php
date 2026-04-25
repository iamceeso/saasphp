<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create table
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->nullable();
            $table->text('value')->nullable();
            $table->string('group')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        // Create or find the "admin" role.
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create or find the "user" role.
        $userRoleId = DB::table('roles')->where('name', 'user')->value('id');
        if (! $userRoleId) {
            $userRoleId = DB::table('roles')->insertGetId([
                'name' => 'user',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create "Admin" user if not exists
        $adminUserId = DB::table('users')
            ->where('email', 'admin@saasphp.com')
            ->value('id');

        if (! $adminUserId) {
            $adminUserId = DB::table('users')->insertGetId([
                'name' => 'Admin User',
                'email' => 'admin@saasphp.com',
                'password' => Hash::make('password'), // <— Change this default if you like
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create "User One" if not exists
        $userOneId = DB::table('users')
            ->where('email', 'user1@saasphp.com')
            ->value('id');

        if (! $userOneId) {
            $userOneId = DB::table('users')->insertGetId([
                'name' => 'User One',
                'email' => 'user1@saasphp.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create "User Two" if not exists
        $userTwoId = DB::table('users')
            ->where('email', 'user2@saasphp.com')
            ->value('id');

        if (! $userTwoId) {
            $userTwoId = DB::table('users')->insertGetId([
                'name' => 'User Two',
                'email' => 'user2@saasphp.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Attach the roles to those users via model_has_roles.
        $exists = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $adminUserId)
            ->where('role_id', $adminRoleId)
            ->exists();

        if (! $exists) {
            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => $adminRoleId,
                'model_type' => User::class,
                'model_id' => $adminUserId,
            ]);
        }

        $exists = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userOneId)
            ->where('role_id', $userRoleId)
            ->exists();

        if (! $exists) {
            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => $userRoleId,
                'model_type' => User::class,
                'model_id' => $userOneId,
            ]);
        }

        $exists = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userTwoId)
            ->where('role_id', $userRoleId)
            ->exists();

        if (! $exists) {
            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => $userRoleId,
                'model_type' => User::class,
                'model_id' => $userTwoId,
            ]);
        }

        /**
         * At this point:
         *  • "roles" table has "admin" and "user"
         *  • "permissions" table has all your defined permissions
         *  • "role_has_permissions" links "admin" -> all perms, "user" -> subset
         *  • "users" table has three rows: admin@saasphp.com, user1@saasphp.com, user2@saasphp.com
         *  • "model_has_roles" links each user to the appropriate role
         */
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
