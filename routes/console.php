<?php

use App\Services\Billing\WebhookService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:webhooks:retry {--max-retries=3}', function (WebhookService $webhookService) {
    $webhookService->retryFailedWebhooks((int) $this->option('max-retries'));

    $this->info('Failed billing webhooks retry pass completed.');
})->purpose('Retry failed billing webhook logs');

Schedule::command('billing:webhooks:retry')->everyFiveMinutes()->withoutOverlapping();
