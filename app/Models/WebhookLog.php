<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'payload',
        'processed',
        'error',
        'attempts',
        'last_attempted_at',
    ];

    protected $casts = [
        'payload' => 'json',
        'processed' => 'boolean',
        'last_attempted_at' => 'datetime',
    ];

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    public function scopeForEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error');
    }

    public function scopeFailedRetries($query, int $maxAttempts = 3)
    {
        return $query->where('attempts', '>=', $maxAttempts)->where('processed', false);
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'processed' => true,
            'error' => null,
            'last_attempted_at' => now(),
        ]);
    }

    public function recordAttempt(string $errorMessage): void
    {
        $this->increment('attempts');
        $this->update([
            'error' => $errorMessage,
            'last_attempted_at' => now(),
        ]);
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return ! $this->processed && $this->attempts < $maxAttempts;
    }
}
