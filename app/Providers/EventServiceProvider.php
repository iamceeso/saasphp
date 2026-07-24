<?php

namespace App\Providers;

use App\Events\ImageUpdated;
use App\Listeners\DeleteOldImage;
use App\Listeners\LogImpersonationStarted;
use App\Listeners\LogImpersonationStopped;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event→listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ImageUpdated::class => [
            DeleteOldImage::class,
        ],
        EnterImpersonation::class => [
            LogImpersonationStarted::class,
        ],
        LeaveImpersonation::class => [
            LogImpersonationStopped::class,
        ],
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
