<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerSubscription extends Model
{
    protected $table = 'customer_subscriptions';

    protected $fillable = [
        'user_id',
        'plan_id',
        'current_subscription_key',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
        'interval',
        'amount',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'canceled_at',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'json',
    ];

    public const CURRENT_SLOT_STATUSES = [
        'trialing',
        'active',
        'past_due',
        'unpaid',
        'incomplete',
        'incomplete_expired',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function billingEvents()
    {
        return $this->hasMany(BillingEvent::class, 'subscription_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trialing', 'active']);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForStripeCustomer($query, string $customerId)
    {
        return $query->where('stripe_customer_id', $customerId);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trialing', 'active']) || $this->onGracePeriod();
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled' || $this->ended_at !== null;
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function onGracePeriod(): bool
    {
        if (!$this->canceled_at) {
            return false;
        }

        return Carbon::now()->lt($this->current_period_end);
    }

    public function shouldOccupyCurrentSlot(): bool
    {
        return in_array($this->status, self::CURRENT_SLOT_STATUSES, true) && $this->ended_at === null;
    }

    public function getFormattedAmount(): string
    {
        $currency = $this->metadata['currency'] ?? 'USD';
        return $currency . ' ' . number_format($this->amount / 100, 2);
    }

    public function getNextBillingDate(): ?Carbon
    {
        if (!$this->current_period_end || $this->ended_at) {
            return null;
        }

        return $this->current_period_end;
    }

    public function daysUntilBillingRenewal(): ?int
    {
        $nextBillingDate = $this->getNextBillingDate();

        if (!$nextBillingDate) {
            return null;
        }

        return Carbon::now()->diffInDays($nextBillingDate, false);
    }
}
