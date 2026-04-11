<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingEvent extends Model
{
    protected $table = 'billing_events';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'event_type',
        'payload',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'json',
        'processed_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    public function scopeForEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeFailed($query)
    {
        return $query->whereNotNull('error_message');
    }

    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update(['error_message' => $errorMessage]);
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function hasFailed(): bool
    {
        return $this->error_message !== null;
    }
}
