<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class SettingPolicy.
 */
class SettingPolicy
{
    public function modify(User $user): bool
    {
        return $user->hasPermissionSafely('modify_settings_role');
    }
}
