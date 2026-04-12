<?php

namespace App\Helpers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleHelper
{
    public static function superAdminRoleName(): string
    {
        return User::superAdminRoleName();
    }

    public static function canManageAssignments(Role $role): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->can('update', $role)) {
            return false;
        }

        if (in_array(strtolower($role->name), [strtolower(static::superAdminRoleName()), 'user'], true)) {
            return $user->hasPermissionTo('assign_core_role');
        }

        return true;
    }

    public static function canDetachRoleFromUser(Role $role, User $user): bool
    {
        if (! static::canManageAssignments($role)) {
            return false;
        }

        if (strtolower($role->name) !== strtolower(static::superAdminRoleName())) {
            return true;
        }

        return static::getAdminCount() > 1 || ! $user->isSuperAdmin();
    }

    public static function getAdminCount(): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', static::superAdminRoleName())
            ->count();
    }
}
