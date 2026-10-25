<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach ([20, 30, 31] as $id) {
    echo "=== PAYMENT $id ===\n";
    $p = App\Models\Payment::find($id);
    if (!$p) {
        echo "Not found.\n";
        continue;
    }
    echo json_encode($p->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    if ($p->order) {
        echo "Order " . $p->order_id . ":\n";
        echo json_encode($p->order->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
