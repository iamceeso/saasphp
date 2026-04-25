<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $target): bool
    {
        return $user->hasPermissionSafely('view_user') && $this->canManageTarget($user, $target);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionSafely('view_any_user');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionSafely('create_user');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermissionSafely('update_user') && $this->canManageTarget($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermissionSafely('delete_user') && $this->canManageTarget($user, $target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionSafely('delete_any_user');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->hasPermissionSafely('restore_user') && $this->canManageTarget($user, $target);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermissionSafely('restore_any_user');
    }

    public function forceDelete(User $user, User $target): bool
    {
        return $user->hasPermissionSafely('force_delete_user') && $this->canManageTarget($user, $target);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermissionSafely('force_delete_any_user');
    }

    public function impersonate(User $user): bool
    {
        return $user->hasPermissionSafely('impersonate_role');
    }

    public function viewAdminRole(User $user): bool
    {
        return $user->hasPermissionSafely('view_admin_role');
    }

    public function viewStaffRole(User $user): bool
    {
        return $user->hasPermissionSafely('view_staff_role');
    }

    public function viewUserRole(User $user): bool
    {
        return $user->hasPermissionSafely('view_user_role');
    }

    public function viewNoRole(User $user): bool
    {
        return $user->hasPermissionSafely('view_no_role_role');
    }

    public function accessPanel(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->hasPrivilegedRole();
    }

    public function byPassMaintenanceRole(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', 'by_pass_maintenance_role');
    }

    protected function canManageTarget(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true;
        }

        if ($target->isSuperAdmin()) {
            return $user->isSuperAdmin();
        }

        if ($target->hasPrivilegedRole()) {
            return $user->isSuperAdmin();
        }

        return $user->hasPrivilegedRole();
    }
}
