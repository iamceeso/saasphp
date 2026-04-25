<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

trait LoadBillingConfig
{
    public function loadBillingConfig(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $stripeConfigMap = [
            'payments.stripe.public_key' => 'services.stripe.public',
            'payments.stripe.secret_key' => 'services.stripe.secret',
            'payments.stripe.webhook_secret' => 'services.stripe.webhook_secret',
        ];

        foreach ($stripeConfigMap as $settingKey => $configKey) {
            $value = Setting::getValue($settingKey);

            if (filled($value)) {
                Config::set($configKey, $value);
            }
        }
    }
}
