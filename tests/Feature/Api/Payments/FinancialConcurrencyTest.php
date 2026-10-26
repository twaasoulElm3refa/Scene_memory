<?php

namespace Tests\Feature\Api\Payments;

use App\Models\FinancialHold;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransactions;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Opt-in race tests for refund holds, withdrawals and the wallet ledger on a
 * disposable MySQL database; no database resets. Competing operations run in
 * separate PHP processes (proc_open, so they also run on Windows) and start
 * together from a shared signal file.
 */
class FinancialConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('FINANCIAL_MYSQL_CONCURRENCY')) {
            $this->markTestSkipped('Requires an isolated MySQL database with FINANCIAL_MYSQL_CONCURRENCY=1.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringStartsWith('scemory_concurrency_test', DB::selectOne('SELECT DATABASE() AS db')->db);
    }

    public function test_competing_withdrawals_never_spend_held_funds(): void
    {
        [$user, $wallet, $payment] = $this->settledTopUp(10000);
        $this->hold($wallet, $payment, 4000, 'REFUND-'.uniqid());

        $results = $this->race(array_fill(0, 4, ['withdraw', ['user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => '30.00']]));

        $this->assertNoErrors($results);
        $this->assertSame(2, count(array_filter($results, 'is_int')), json_encode($results));
        $this->assertSame(2, count(array_keys($results, 'rejected', true)));
        $this->assertSame(4000, $wallet->fresh()->balance_minor);
        $this->assertEquals(60, $wallet->fresh()->pending);
    }

    public function test_concurrent_refund_deliveries_hold_once_per_refund_and_never_more_than_was_credited(): void
    {
        [, $wallet, $payment] = $this->settledTopUp(10000);
        $full = $this->refund($payment, 'REFUND-FULL-'.uniqid(), '100.00');
        $jobs = array_fill(0, 3, ['reversal', ['payload' => $full]]);
        foreach (range(1, 3) as $index) {
            $jobs[] = ['reversal', ['payload' => $this->refund($payment, 'REFUND-PART-'.$index.'-'.uniqid(), '40.00')]];
        }

        $results = $this->race($jobs);

        $this->assertNoErrors($results);
        $this->assertSame(10000, FinancialHold::heldMinor($wallet->id));
        $this->assertSame(0, FinancialHold::where('payment_id', $payment->id)
            ->groupBy('provider_reference')->havingRaw('COUNT(*) > 1')->count('provider_reference'));
        $this->assertSame(10000, $wallet->fresh()->balance_minor);
    }

    public function test_a_refund_hold_racing_withdrawals_never_lets_both_take_the_money(): void
    {
        [$user, $wallet, $payment] = $this->settledTopUp(10000);
        $jobs = [['reversal', ['payload' => $this->refund($payment, 'REFUND-RACE-'.uniqid(), '100.00')]]];
        $jobs[] = ['withdraw', ['user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => '60.00']];
        $jobs[] = ['withdraw', ['user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => '60.00']];

        $results = $this->race($jobs);

        $this->assertNoErrors($results);
        $this->assertSame('ok', $results[0]);
        $this->assertSame(10000, FinancialHold::heldMinor($wallet->id));
        // Either the hold won and both withdrawals were refused, or one withdrawal
        // reserved first and the hold then covers what an administrator must recover.
        $this->assertLessThanOrEqual(1, Withdraw::where('user_id', $user->id)->count());
        $fresh = $wallet->fresh();
        $this->assertSame(10000, $fresh->balance_minor + (int) round($fresh->pending * 100));
    }

    public function test_an_admin_release_racing_a_withdrawal_stays_consistent(): void
    {
        [$user, $wallet, $payment] = $this->settledTopUp(10000);
        $hold = $this->hold($wallet, $payment, 10000, 'REFUND-'.uniqid());

        $results = $this->race([
            ['release', ['hold_id' => $hold->id]],
            ['withdraw', ['user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => '100.00']],
        ]);

        $this->assertNoErrors($results);
        $this->assertSame('released', $results[0]);
        $this->assertContains($results[1], ['rejected', Withdraw::where('user_id', $user->id)->value('id')]);
        $fresh = $wallet->fresh();
        $this->assertSame(10000, $fresh->balance_minor + (int) round($fresh->pending * 100));
    }

    public function test_concurrent_ledger_posts_with_one_idempotency_key_move_money_once(): void
    {
        [$user, $wallet, $payment] = $this->settledTopUp(10000);
        $key = 'seller-credit:'.$payment->id.':race-'.uniqid();

        $results = $this->race(array_fill(0, 4, ['ledger', ['wallet_id' => $wallet->id, 'order_id' => $payment->order_id, 'amount' => 500, 'key' => $key]]));

        $this->assertNoErrors($results);
        $this->assertCount(1, array_unique($results));
        $this->assertSame(1, WalletTransactions::where('idempotency_key', $key)->count());
        $this->assertSame(10500, $wallet->fresh()->balance_minor);
    }

    /**
     * Starts every job in its own PHP process and releases them together.
     *
     * @param  array<int, array{0: string, 1: array}>  $jobs
     * @return array<int, mixed>
     */
    private function race(array $jobs): array
    {
        $start = tempnam(sys_get_temp_dir(), 'scemory-race-');
        unlink($start);
        $phpArgs = array_values(array_filter(explode(' ', (string) getenv('CONCURRENCY_PHP_ARGS'))));
        $workers = [];
        foreach ($jobs as $index => [$scenario, $args]) {
            $command = [PHP_BINARY, ...$phpArgs, base_path('tests/Support/financial-concurrency-worker.php'), $scenario, $start, json_encode($args)];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                throw new RuntimeException('Could not start worker '.$index);
            }
            $workers[$index] = [$process, $pipes];
        }
        try {
            foreach ($workers as $index => [, $pipes]) {
                // PHP start-up notices may precede the handshake on stdout.
                $seen = '';
                while (($line = fgets($pipes[1])) !== false && trim($line) !== 'READY') {
                    $seen .= $line;
                }
                if ($line === false) {
                    throw new RuntimeException('Worker '.$index.' did not start: '.$seen.stream_get_contents($pipes[2]));
                }
            }
            touch($start);
            $results = [];
            foreach ($workers as $index => [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $lines = preg_split('/\R/', trim((string) $output));
                $decoded = json_decode((string) end($lines), true);
                $results[$index] = is_array($decoded) && array_key_exists('result', $decoded)
                    ? ($decoded['result'] === 'error' ? 'error: '.$decoded['class'].': '.$decoded['message'] : $decoded['result'])
                    : 'error: unreadable worker output: '.$output.$errors;
            }

            return $results;
        } finally {
            @unlink($start);
        }
    }

    private function assertNoErrors(array $results): void
    {
        foreach ($results as $result) {
            $this->assertFalse(is_string($result) && str_starts_with($result, 'error'), (string) $result);
            $this->assertNotSame('timeout', $result);
        }
    }

    /**
     * @return array{0: User, 1: Wallet, 2: Payment}
     */
    private function settledTopUp(int $amountMinor): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $wallet = Wallet::create(['user_id' => $user->id, 'amount' => number_format($amountMinor / 100, 2, '.', ''), 'balance_minor' => $amountMinor, 'pending' => 0, 'currency' => 'USD']);
        $order = Purchases::create(['user_id' => $user->id, 'payment_method' => 'paypal', 'type' => 'wallet_deposit', 'order_type' => 'wallet_deposit',
            'status' => 'completed', 'currency' => 'USD', 'amount' => number_format($amountMinor / 100, 2, '.', ''), 'amount_minor' => $amountMinor,
            'idempotency_key' => 'race-order-'.uniqid('', true)]);
        $payment = Payment::create(['order_id' => $order->id, 'user_id' => $user->id, 'operation' => 'wallet_deposit', 'method' => 'paypal',
            'status' => 'completed', 'amount_minor' => $amountMinor, 'currency' => 'USD', 'idempotency_key' => 'race-pay-'.uniqid('', true),
            'paypal_order_id' => 'RACE-ORDER-'.uniqid(), 'capture_id' => 'RACE-CAPTURE-'.uniqid(), 'paid_at' => now()]);
        $payment->update(['custom_id' => 'wallet_topup:'.$payment->id, 'reference_id' => (string) $payment->id]);
        $order->update(['paypal_order_id' => $payment->paypal_order_id]);

        return [$user, $wallet, $payment->fresh()];
    }

    private function hold(Wallet $wallet, Payment $payment, int $amountMinor, string $reference): FinancialHold
    {
        return FinancialHold::create(['wallet_id' => $wallet->id, 'user_id' => $wallet->user_id, 'payment_id' => $payment->id, 'reason' => 'refund',
            'provider_reference' => $reference, 'amount_minor' => $amountMinor, 'currency' => 'USD', 'status' => 'active']);
    }

    private function refund(Payment $payment, string $refundId, string $value): array
    {
        return ['id' => 'WH-'.$refundId, 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => [
            'id' => $refundId, 'status' => 'COMPLETED', 'amount' => ['value' => $value, 'currency_code' => 'USD'],
            'supplementary_data' => ['related_ids' => ['capture_id' => $payment->capture_id, 'order_id' => $payment->paypal_order_id]],
        ]];
    }
}
