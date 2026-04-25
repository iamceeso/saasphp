<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_any_role');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_role');
    }

    public function update(User $user, Role $role): bool
    {
        $name = strtolower($role->name);

        if ($name === 'admin') {
            return $user->hasPermissionTo('update_admin_role');
        }

        if (! in_array($name, ['admin', 'user'])) {
            return $user->hasPermissionTo('update_staff_role');
        }

        return $user->hasPermissionTo('update_role');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('delete_role') &&
            ! in_array(strtolower($role->name), ['admin', 'user']) &&
            $role->users()->count() === 0;
    }

    public function viewSites(User $user): bool
    {
        return $user->hasPermissionTo('view_sites');
    }

    public function viewSocial(User $user): bool
    {
        return $user->hasPermissionTo('view_social');
    }

    public function viewFeatures(User $user): bool
    {
        return $user->hasPermissionTo('view_features');
    }

    public function viewEmail(User $user): bool
    {
        return $user->hasPermissionTo('view_email');
    }

    public function viewSms(User $user): bool
    {
        return $user->hasPermissionTo('view_sms');
    }

    public function modifySettings(User $user): bool
    {
        return $user->hasPermissionTo('modify_settings_role');
    }

    public function assignCore(User $user): bool
    {
        return $user->hasPermissionTo('assign_core_role');
    }

    public function impersonate(User $user): bool
    {
        return $user->hasPermissionTo('impersonate_role');
    }

    public function viewAdmin(User $user): bool
    {
        return $user->hasPermissionTo('view_admin_role');
    }

    public function viewStaff(User $user): bool
    {
        return $user->hasPermissionTo('view_staff_role');
    }

    public function viewUser(User $user): bool
    {
        return $user->hasPermissionTo('view_user_role');
    }

    public function viewNoRole(User $user): bool
    {
        return $user->hasPermissionTo('view_no_role_role');
    }

    public function byPassMaintenanceRole(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', 'by_pass_maintenance_role');
    }
}
