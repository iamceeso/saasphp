<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

class LogImpersonationStopped
{
    public function handle(LeaveImpersonation $event): void
    {
        Log::info('User impersonation stopped.', [
            'impersonator_id' => $event->impersonator->getAuthIdentifier(),
            'impersonated_id' => $event->impersonated?->getAuthIdentifier(),
        ]);
    }
}
