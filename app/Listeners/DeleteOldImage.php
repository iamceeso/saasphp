<?php

namespace App\Listeners;

use App\Events\ImageUpdated;
use Illuminate\Support\Facades\Storage;

class DeleteOldImage
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ImageUpdated $event): void
    {
        foreach ((array) $event->paths as $path) {
            if (! empty($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
