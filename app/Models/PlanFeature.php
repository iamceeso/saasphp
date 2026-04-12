<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id',
        'feature_key',
        'feature_name',
        'description',
        'value',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function getValue(): mixed
    {
        if ($this->value === null) {
            return true;
        }

        if (in_array($this->value, ['true', 'false'])) {
            return $this->value === 'true';
        }

        if (is_numeric($this->value)) {
            return intval($this->value);
        }

        return $this->value;
    }

    public function isUnlimited(): bool
    {
        return is_string($this->value) && strtolower($this->value) === 'unlimited';
    }
}
