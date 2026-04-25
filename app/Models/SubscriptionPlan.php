<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int|null $sort_order
 * @property bool $is_active
 * @property bool $is_most_popular
 * @property string $cta_type
 * @property string|null $contact_url
 * @property string|null $contact_button_text
 * @property string|null $stripe_product_id
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
        'is_most_popular',
        'cta_type',
        'contact_url',
        'contact_button_text',
        'stripe_product_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_most_popular' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $plan): void {
            if (! $plan->is_most_popular) {
                return;
            }

            static::query()
                ->whereKeyNot($plan->getKey())
                ->where('is_most_popular', true)
                ->update(['is_most_popular' => false]);
        });
    }

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

    public function markAsMostPopular(): void
    {
        $this->forceFill(['is_most_popular' => true])->save();
    }

    public function isContactPlan(): bool
    {
        return $this->cta_type === 'contact';
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
