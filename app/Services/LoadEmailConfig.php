<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait LoadEmailConfig
{
    /**
     * Load email service configuration dynamically from database.
     */
    public function loadEmailConfig(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (! Setting::getBooleanValue('features.email_sending', false)) {
            return;
        }

        // Retrieve common settings
        $client = Setting::getValue('email.client_name');
        $from = Setting::getValue('email.from.address', 'no-reply@example.com');
        [$local, $domain] = explode('@', $from, 2);
        $siteName = Setting::getValue('site.name', 'Built with SaaS PHP');

        // Silent warning message
        if (! $from || ! $client) {
            Log::warning('Email config not fully set:', [
                'client' => $client,
                'from' => $from,
            ]);
        }

        // Apply global "from" configuration
        Config::set('mail.from.address', $from);
        Config::set('mail.from.name', $siteName);

        // Build client-specific configuration
        switch ($client) {
            case 'mailgun':
                Config::set('services.mailgun.domain', $domain);
                Config::set('services.mailgun.secret', Setting::getValue('email.mailgun.api_key'));
                Config::set('services.mailgun.endpoint', 'api.eu.mailgun.net');
                Config::set('mail.default', 'mailgun');
                break;

            case 'resend':
                Config::set('services.resend.key', Setting::getValue('email.resend.api_key'));
                Config::set('mail.default', 'resend');
                break;

            case 'postmark':
                Config::set('services.postmark.token', Setting::getValue('email.postmark.api_key'));
                Config::set('mail.default', 'postmark');
                break;

            case 'log':
            default:
                Config::set('mail.default', 'log');
                break;
        }
    }
}
