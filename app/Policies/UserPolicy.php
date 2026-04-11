<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Setting;

class UserPolicy
{
    public function view(User $user, User $target): bool
    {
        return $user->hasPermissionTo('view_user') && $this->canManageTarget($user, $target);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_any_user');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_user');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermissionTo('update_user') && $this->canManageTarget($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermissionTo('delete_user') && $this->canManageTarget($user, $target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete_any_user');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->hasPermissionTo('restore_user') && $this->canManageTarget($user, $target);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermissionTo('restore_any_user');
    }

    public function forceDelete(User $user, User $target): bool
    {
        return $user->hasPermissionTo('force_delete_user') && $this->canManageTarget($user, $target);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermissionTo('force_delete_any_user');
    }

    public function impersonate(User $user): bool
    {
        return $user->hasPermissionTo('impersonate_role');
    }

    public function viewAdminRole(User $user): bool
    {
        return $user->hasPermissionTo('view_admin_role');
    }

    public function viewStaffRole(User $user): bool
    {
        return $user->hasPermissionTo('view_staff_role');
    }

    public function viewUserRole(User $user): bool
    {
        return $user->hasPermissionTo('view_user_role');
    }

    public function viewNoRole(User $user): bool
    {
        return $user->hasPermissionTo('view_no_role_role');
    }

    public function accessPanel(User $user): bool
    {
        $domainAllowed = str_ends_with($user->email, '@' . Setting::getValue('site.url', 'saasphp.com'));
        $roleAllowed = $user->roles->isNotEmpty() && !$user->hasRole('user');

        return $domainAllowed && $roleAllowed;
    }

    public function byPassMaintenanceRole(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', 'by_pass_maintenance_role');
    }

    protected function canManageTarget(User $user, User $target): bool
    {
        if ($target->hasRole(config('filament-shield.super_admin.name', 'admin'))) {
            return $user->hasRole(config('filament-shield.super_admin.name', 'admin'));
        }

        return true;
    }
}
