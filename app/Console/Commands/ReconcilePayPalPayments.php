<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PayPalServices;
use App\Services\PayPalWalletServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePayPalPayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=100} {--age=10}';

    protected $description = 'Reconcile old pending PayPal purchases and wallet deposits';

    public function handle(PayPalServices $purchases, PayPalWalletServices $deposits): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $age = max(1, (int) $this->option('age'));
        $stats = ['checked' => 0, 'completed' => 0, 'pending' => 0, 'failed' => 0];
        Log::info('reconciliation_started', compact('limit', 'age'));

        Payment::query()
            ->where('method', 'paypal')
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('paypal_order_id')
            ->where('created_at', '<=', now()->subMinutes($age))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Payment $payment) use ($purchases, $deposits, &$stats) {
                $stats['checked']++;
                try {
                    $result = $payment->operation === 'wallet_deposit'
                        ? $deposits->reconcile($payment)
                        : $purchases->reconcile($payment);
                    $stats[$result === 'completed' ? 'completed' : 'pending']++;
                } catch (Throwable $exception) {
                    $stats['failed']++;
                    Log::warning('reconciliation_payment_failed', [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'exception' => $exception::class,
                    ]);
                }
            });

        Log::info('reconciliation_completed', $stats);
        $this->info(json_encode($stats, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
