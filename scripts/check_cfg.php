<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cfg = config('paypal');
echo "MODE: " . ($cfg['mode'] ?? 'null') . "\n";
echo "SANDBOX CLIENT ID SET: " . (!empty($cfg['sandbox']['client_id']) ? 'YES' : 'NO') . "\n";
echo "LIVE CLIENT ID SET: " . (!empty($cfg['live']['client_id']) ? 'YES' : 'NO') . "\n";
echo "ENV PAYPAL_MODE: " . env('PAYPAL_MODE') . "\n";
echo "ENV PAYPAL_SANDBOX_CLIENT_ID SET: " . (!empty(env('PAYPAL_SANDBOX_CLIENT_ID')) ? 'YES' : 'NO') . "\n";
echo "IS CONFIG CACHED: " . ($app->configurationIsCached() ? 'YES' : 'NO') . "\n";
