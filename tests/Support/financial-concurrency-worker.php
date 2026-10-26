<?php

/*
 * One competing financial operation for FinancialConcurrencyTest. The worker boots
 * the application, connects, reports READY, then waits for the shared start file
 * so every worker hits the database at the same moment.
 *
 * Usage: php financial-concurrency-worker.php <scenario> <start-file> <json-args>
 */

use App\Models\FinancialHold;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayPalReversalReview;
use App\Services\WalletLedgerService;
use App\Services\WithdrawalReservationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $scenario, $startFile, $json] = $argv + [null, '', '', '{}'];
$args = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

$database = (string) DB::connection()->selectOne('SELECT DATABASE() AS db')->db;
if (config('database.default') !== 'mysql' || ! str_starts_with($database, 'scemory_concurrency_test')) {
    echo json_encode(['result' => 'refused']);
    exit(2);
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
$deadline = microtime(true) + 30;
while (! file_exists($startFile)) {
    if (microtime(true) > $deadline) {
        echo json_encode(['result' => 'timeout']);
        exit(3);
    }
    usleep(200);
}

try {
    $result = match ($scenario) {
        'withdraw' => app(WithdrawalReservationService::class)
            ->create(User::findOrFail($args['user_id']), (int) $args['wallet_id'], ['amount' => $args['amount']])->id,
        'reversal' => app(PayPalReversalReview::class)->flag($args['payload']),
        // Like every production caller, the ledger post runs under the wallet lock.
        'ledger' => DB::transaction(fn () => app(WalletLedgerService::class)->post(
            Wallet::query()->lockForUpdate()->findOrFail($args['wallet_id']),
            Purchases::findOrFail($args['order_id']),
            null, 'credit', 'media_sale', (int) $args['amount'], (string) $args['key'], 'concurrency-test',
        )->id, 5),
        'release' => DB::transaction(function () use ($args) {
            $hold = FinancialHold::query()->lockForUpdate()->findOrFail($args['hold_id']);
            $hold->update(['status' => 'released', 'resolved_at' => now(), 'resolution_note' => 'concurrency test']);

            return 'released';
        }, 5),
        default => throw new InvalidArgumentException('Unknown scenario.'),
    };
    echo json_encode(['result' => $result]);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected']);
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'class' => $exception::class, 'message' => substr($exception->getMessage(), 0, 300)]);
}
