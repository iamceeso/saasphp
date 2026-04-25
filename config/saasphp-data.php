<?php

return [
    'siteSettings' => [
        'site.name' => ['value' => 'SaaS PHP', 'type' => 'string'],
        'site.url' => ['value' => 'saasphp.com', 'type' => 'string'],
        'site.description' => ['value' => 'SaaS PHP is a powerful SaaS application framework.', 'type' => 'string'],
        'site.logo' => ['value' => 'logos/logo.png', 'type' => 'string'],
        'site.theme' => ['value' => 'default', 'type' => 'string'],
        'site.timezone' => ['value' => 'UTC', 'type' => 'string'],
        'site.currency' => ['value' => 'USD', 'type' => 'string'],
        'site.language' => ['value' => 'en', 'type' => 'string'],
        'site.date_format' => ['value' => 'Y-m-d', 'type' => 'string'],
        'site.time_format' => ['value' => 'H:i:s', 'type' => 'string'],
    ],

    'socialProviders' => [
        'social.github.client_id' => ['value' => 'your_github_client_id', 'type' => 'string'],
        'social.github.client_secret' => ['value' => 'your_github_client_secreate', 'type' => 'string'],
        'social.github.redirect_uri' => ['value' => '/auth/github/callback', 'type' => 'string'],

        'social.twitter.client_id' => ['value' => 'your_x_client_id', 'type' => 'string'],
        'social.twitter.client_secret' => ['value' => 'your_x_client_secret', 'type' => 'string'],
        'social.twitter.redirect_uri' => ['value' => 'http://saasphp.test/auth/twitter/callback', 'type' => 'string'],

        'social.google.client_id' => ['value' => 'your_google_client_id.apps.googleusercontent.com', 'type' => 'string'],
        'social.google.client_secret' => ['value' => 'your_google_client_secret', 'type' => 'string'],
        'social.google.redirect_uri' => ['value' => '/auth/google/callback', 'type' => 'string'],

        'social.yahoo.client_id' => ['value' => 'your_yahoo_client_id', 'type' => 'string'],
        'social.yahoo.client_secret' => ['value' => 'your_yahoo_client_secret', 'type' => 'string'],
        'social.yahoo.redirect_uri' => ['value' => '/auth/yahoo/callback', 'type' => 'string'],

        'social.microsoft.client_id' => ['value' => 'your_microsoft_client_id', 'type' => 'string'],
        'social.microsoft.client_secret' => ['value' => 'your_microsoft_client_secret', 'type' => 'string'],
        'social.microsoft.redirect_uri' => ['value' => '/auth/microsoft/callback', 'type' => 'string'],
    ],

    'paymentGateways' => [

        'payments.stripe.public_key' => ['value' => 'pk_test_xxx', 'type' => 'string'],
        'payments.stripe.secret_key' => ['value' => 'sk_test_xxx', 'type' => 'string'],
        'payments.stripe.webhook_secret' => ['value' => 'whsec_xxx', 'type' => 'string'],

        'payments.paystack.public_key' => ['value' => 'pk_test_paystack', 'type' => 'string'],
        'payments.paystack.secret_key' => ['value' => 'sk_test_paystack', 'type' => 'string'],

        'payments.paddle.vendor_id' => ['value' => '123456', 'type' => 'string'],
        'payments.paddle.api_key' => ['value' => 'your-paddle-api-key', 'type' => 'string'],
        'payments.paddle.public_key' => ['value' => '-----BEGIN PUBLIC KEY-----...', 'type' => 'string'],
    ],

    'featuresSettings' => [
        'features.enable_registration' => ['value' => true, 'type' => 'boolean'],
        'features.enable_email_verification' => ['value' => true, 'type' => 'boolean'],
        'features.enable_phone_verification' => ['value' => true, 'type' => 'boolean'],
        'features.enable_two_factor_auth' => ['value' => true, 'type' => 'boolean'],
        'features.email_sending' => ['value' => true, 'type' => 'boolean'],
        'features.sms_sending' => ['value' => true, 'type' => 'boolean'],
        'features.enable_confirm_password' => ['value' => true, 'type' => 'boolean'],
        'features.phone_email_at_registration' => ['value' => true, 'type' => 'boolean'],
        'features.maintenance_mode' => ['value' => false, 'type' => 'boolean'],
    ],

    'emailSettings' => [
        'email.client_name' => ['value' => 'resend',   'type' => 'string'],
        'email.resend.api_key' => ['value' => 're_send_api_key', 'type' => 'string'],
        'email.mailgun.api_key' => ['value' => 'mail_gun_key', 'type' => 'string'],
        'email.mailgun.endpoint' => ['value' => 'api.mailgun.net', 'type' => 'string'],
        'email.from.address' => ['value' => 'onboarding@resend.dev', 'type' => 'string'],
        'email.postmark.api_key' => ['value' => 'post_mark_key', 'type' => 'string'],
    ],

    'smsSettings' => [
        'sms.client_name' => ['value' => 'vonage',   'type' => 'string'],
        'sms.from.address' => ['value' => 'SaaS PHP', 'type' => 'string'],
        'sms.vonage.api_key' => ['value' => 'vonage_api_key', 'type' => 'string'],
        'sms.vonage.api_secret' => ['value' => 'vonage_api_secret', 'type' => 'string'],
        'sms.africa_talking.username' => ['value' => 'saasPHP', 'type' => 'string'],
        'sms.africa_talking.api_key' => ['value' => 'africa_talking_api_key', 'type' => 'string'],
    ],
];
