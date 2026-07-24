<?php

namespace App\Support;

use App\Models\User;
use STS\FilamentImpersonate\ImpersonateManager;

/**
 * Manages impersonator session state for admin user switching.
 */
class ImpersonationSession
{
    public const LOCAL_SESSION_KEY = 'impersonator_id';

    public static function isImpersonating(): bool
    {
        return session()->has(self::LOCAL_SESSION_KEY)
            || session()->has(ImpersonateManager::SESSION_KEY);
    }

    public static function impersonatorId(): int|string|null
    {
        return session(self::LOCAL_SESSION_KEY)
            ?? session(ImpersonateManager::SESSION_KEY);
    }

    public static function impersonator(): ?User
    {
        $impersonatorId = self::impersonatorId();

        return $impersonatorId ? User::find($impersonatorId) : null;
    }
}
