<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use STS\FilamentImpersonate\Events\EnterImpersonation;

/**
 * Class LogImpersonationStarted.
 */
class LogImpersonationStarted
{
    public function handle(EnterImpersonation $event): void
    {
        Log::info('User impersonation started.', [
            'impersonator_id' => $event->impersonator->getAuthIdentifier(),
            'impersonated_id' => $event->impersonated->getAuthIdentifier(),
        ]);
    }
}
