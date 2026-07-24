<?php

namespace App\Helpers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Provides role naming and visibility helper utilities.
 */
class RoleHelper
{
    public static function superAdminRoleName(): string
    {
        return User::superAdminRoleName();
    }

    public static function canManageRolePermissions(?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) (
            $user?->isSuperAdmin()
            || $user?->hasPermissionSafely('assign_core_role')
        );
    }

    public static function assertCanSubmitRolePermissions(Collection $submitted, ?Collection $original = null): void
    {
        if (static::canManageRolePermissions()) {
            return;
        }

        $original ??= collect();

        if ($submitted->sort()->values()->all() !== $original->sort()->values()->all()) {
            abort(403, 'You are not authorized to modify role-related permissions.');
        }
    }

    public static function roleHasRoleManagementPermissions(Role $role): bool
    {
        return $role->permissions
            ->pluck('name')
            ->contains(fn (string $name): bool => str($name)->endsWith('_role'));
    }

    public static function canManageAssignments(Role $role): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->can('update', $role)) {
            return false;
        }

        if (in_array(strtolower($role->name), [strtolower(static::superAdminRoleName()), 'user'], true)) {
            return $user->hasPermissionSafely('assign_core_role');
        }

        if (static::roleHasRoleManagementPermissions($role)) {
            return static::canManageRolePermissions($user);
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
