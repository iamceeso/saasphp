<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
        'stripe_product_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class, 'plan_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getMonthlyPrice(): ?PlanPrice
    {
        return $this->prices()
            ->where('interval', 'monthly')
            ->where('is_active', true)
            ->first();
    }

    public function getAnnuallyPrice(): ?PlanPrice
    {
        return $this->prices()
            ->where('interval', 'annually')
            ->where('is_active', true)
            ->first();
    }

    public function hasFeature(string $featureKey): bool
    {
        return $this->features()
            ->where('feature_key', $featureKey)
            ->exists();
    }

    public function getFeature(string $featureKey): ?PlanFeature
    {
        return $this->features()
            ->where('feature_key', $featureKey)
            ->first();
    }
}
