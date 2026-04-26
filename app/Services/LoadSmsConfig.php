<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use App\Enums\SmsProviders;
use App\Models\PhoneCode;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Vonage\Client as VonageClient;
use Vonage\Client\Credentials\Basic as VonageCredentials;
use Vonage\SMS\Message\SMS;

trait LoadSmsConfig
{
    public function loadDynamicSmsConfig(?string $message = null, ?string $phone = null): void
    {
        $user = Auth::user();
        if (! Schema::hasTable('settings')) {
            return;
        }

        // Check if SMS sending is enabled
        if (! Setting::getBooleanValue('features.sms_sending', false)) {
            return;
        }

        $code = rand(100000, 999999);
        $expiresAt = now()->addMinutes(5);

        if (Auth::check()) {
            // Invalidate previous unused codes
            PhoneCode::where('user_id', $user->id)->whereNull('used_at')->delete();

            // Save hashed code
            PhoneCode::create([
                'user_id' => $user->id,
                'code' => Hash::make($code),
                'expires_at' => $expiresAt,
            ]);
        }

        $client = Setting::getValue('sms.client_name');
        $from = Setting::getValue('sms.from.address');

        // Use passed phone number or fall back to $this->phone
        $recipient = $phone ?? $user->phone;

        if (! $recipient) {
            return;
        }

        $finalMessage = $message ?? "Your code is: {$code}";

        if ($client === SmsProviders::VONAGE->value) {
            config()->set('services.vonage.key', Setting::getValue('sms.vonage.api_key'));
            config()->set('services.vonage.secret', Setting::getValue('sms.vonage.api_secret'));
            config()->set('services.vonage.sms_from', $from);

            $credentials = new VonageCredentials(
                config('services.vonage.key'),
                config('services.vonage.secret')
            );
            $client = new VonageClient($credentials);

            $messageText = $finalMessage;
            $client->sms()->send(
                new SMS($recipient, config('services.vonage.sms_from'), $messageText)
            );
        }
        if ($client === SmsProviders::AFRICA_TALKING->value) {

            config()->set('services.africa_talking.username', Setting::getValue('sms.africa_talking.username'));
            config()->set('services.africa_talking.api_key', Setting::getValue('sms.africa_talking.api_key'));

            $username = Setting::getValue('sms.africa_talking.username');
            $apiKey = Setting::getValue('sms.africa_talking.api_key');
            $AT = new AfricasTalking($username, $apiKey);
            $sms = $AT->sms();

            $sms->send([
                'to' => $recipient,
                'message' => $finalMessage,
            ]);
        }
    }
}
