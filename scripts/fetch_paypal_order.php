<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateway = app(\App\Services\PayPalGateway::class);

foreach (['5CU15830YP889252J', '0PE31420J3554614E'] as $orderId) {
    echo "========================================\n";
    echo "FETCHING ORDER: $orderId\n";
    echo "========================================\n";
    try {
        $details = $gateway->showOrderDetails($orderId);
        // Clean out any sensitive auth or tokens if any
        unset($details['access_token']);
        echo json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
