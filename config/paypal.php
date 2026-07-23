<?php

/**
 * PayPal Setting & API Credentials
 * Created by Raza Mehdi <srmk@outlook.com>.
 */

return [
    'mode' => env('PAYPAL_MODE', 'sandbox'), // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
    'sandbox' => [
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET', ''),
        'app_id' => env('PAYPAL_SANDBOX_APP_ID', 'APP-80W284485P519543T'),
    ],
    'live' => [
        'client_id' => env('PAYPAL_LIVE_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET', ''),
        'app_id' => env('PAYPAL_LIVE_APP_ID', ''),
    ],

    'payment_action' => env('PAYPAL_PAYMENT_ACTION', 'Sale'), // Can only be 'Sale', 'Authorization' or 'Order'
    'currency' => env('PAYPAL_CURRENCY', 'USD'),
    'notify_url' => env('PAYPAL_NOTIFY_URL', ''), // Change this accordingly for your application.
    'locale' => env('PAYPAL_LOCALE', 'en_US'), // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
    'validate_ssl' => env('PAYPAL_VALIDATE_SSL', true),
    'webhooks' => [
        'checkout' => env('PAYPAL_WEBHOOK_ID', env('PAYPAL_CHECKOUT_WEBHOOK_ID', '')),
        'wallet' => env('PAYPAL_WALLET_WEBHOOK_ID', ''),
    ],
    'webhook_id' => env('PAYPAL_WEBHOOK_ID', env('PAYPAL_CHECKOUT_WEBHOOK_ID', '')),
    'wallet_webhook_id' => env('PAYPAL_WALLET_WEBHOOK_ID', ''),
    'merchant_id' => env('PAYPAL_MERCHANT_ID'),
    'verify_webhooks' => env('PAYPAL_VERIFY_WEBHOOKS', true),
    'allow_local_webhook_bypass' => env('PAYPAL_ALLOW_LOCAL_WEBHOOK_BYPASS', false),
    'api' => [
        'sandbox_url' => env('PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com'),
        'live_url' => env('PAYPAL_LIVE_API_URL', 'https://api-m.paypal.com'),
        'timeout' => (int) env('PAYPAL_API_TIMEOUT', 10),
        'retry_times' => (int) env('PAYPAL_API_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('PAYPAL_API_RETRY_SLEEP_MS', 200),
    ],
];
