<?php

namespace App\Models;

use App\Models\Setting;

use App\Services\LoadSmsConfig;
use App\Services\LoadEmailConfig;

use Filament\Models\Contracts\FilamentUser;

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles, SoftDeletes, LoadEmailConfig, LoadSmsConfig, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string>
     */
    protected $casts = [
        'email_verified_at'         => 'datetime',
        'phone_verified_at'         => 'datetime',
        'password'                  => 'hashed',            // ← hashed cast
        'two_factor_secret'         => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
    ];

    protected static function booted()
    {
        static::created(function (User $user) {
            // Make sure the default role exists in DB
            $user->assignRole('user'); // or whatever your default role name is
        });
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return Gate::allows('accessPanel', static::class);
    }

    public static function superAdminRoleName(): string
    {
        return (string) config('filament-shield.super_admin.name', 'admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(static::superAdminRoleName());
    }

    public function hasPrivilegedRole(): bool
    {
        return $this->roles->contains(function ($role) {
            $name = strtolower($role->name);

            return $name !== 'user';
        });
    }

    public function isStandardUser(): bool
    {
        return $this->hasRole('user') && ! $this->hasPrivilegedRole();
    }

    public function hasVerifiedEmail()
    {
        // Skip check if verification is disabled in settings
        if (!Setting::getBooleanValue('features.enable_email_verification', true)) {
            return true;
        }

        return !is_null($this->email_verified_at);
    }

    public function hasVerifiedPhone()
    {
        if (!Setting::getBooleanValue('features.enable_phone_verification', true)) {
            return true;
        }

        return !is_null($this->phone_verified_at);
    }

    public function markEmailAsVerified()
    {
        if ($this->hasVerifiedEmail()) {
            return true;
        }

        // Ensure the email is set before marking as verified
        return $this->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }

    public function markPhoneAsVerified()
    {
        if ($this->hasVerifiedPhone()) {
            return true;
        }

        return $this->forceFill([
            'phone_verified_at' => now(),
        ])->save();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function billingEvents(): HasMany
    {
        return $this->hasMany(BillingEvent::class);
    }

    public function getCurrentSubscription(): ?CustomerSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->exists();
    }

    public function sendVerificationEmailWithRateLimit(): bool
    {
        $key = 'verify-email:' . $this->id;
        $attemptCacheKey = "{$key}:attempts";

        if ((int) cache()->get($attemptCacheKey, 0) >= 3) {
            return false;
        }

        $incrementAttempts = function () use ($attemptCacheKey): void {
            cache()->add($attemptCacheKey, 0, now()->addMinutes(15));
            cache()->increment($attemptCacheKey);
        };

        if (
            app()->environment(['local', 'testing']) &&
            in_array(config('mail.default'), ['array', 'log'], true)
        ) {
            $incrementAttempts();

            return true;
        }

        try {
            $this->loadEmailConfig();

            // Attempt to send
            $this->sendEmailVerificationNotification();

            $incrementAttempts();

            return true;
        } catch (\Throwable $e) {
            // Log failure, never crash request
            \Log::warning('Email verification failed', [
                'user_id' => $this->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendPhoneVerificationCodeWithRateLimit(): bool
    {
        $key = 'verify-phone:' . $this->id;

        if (RateLimiter::attempts($key) >= 3) {
            return false;
        }

        $this->loadDynamicSmsConfig();

        RateLimiter::hit($key, now()->diffInSeconds(now()->addMinutes(15)));
        return true;
    }

    // public function sendPasswordResetNotification($token)
    // {
    //     if ($this->email) {
    //         $this->loadEmailConfig();
    //         $this->notify(new ResetPasswordNotification($token));
    //         return;
    //     }

    //     // Your LoadSmsConfig trait expects a "code" – just pass it the token:
    //     $message = "Your password reset code is: {$token}";
    //     $this->loadDynamicSmsConfig($token, $message);
    // }
}
