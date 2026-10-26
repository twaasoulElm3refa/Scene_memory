<?php

namespace App\Services;

use App\Models\FinancialHold;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdraw;
use App\Support\SecurityMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalReservationService
{
    public function __construct(private readonly MinorMoney $money) {}

    private function invalid(): never
    {
        throw ValidationException::withMessages(['amount' => SecurityMessage::get('invalidWithdrawal')]);
    }

    private function lockedWallet(int $userId): Wallet
    {
        $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
        if ($wallet->balance_minor !== null && $wallet->balance_minor !== $this->money->fromDecimal($wallet->amount)) {
            // Do not silently reconcile legacy financial discrepancies.
            $this->invalid();
        }

        return $wallet;
    }

    private function amount(mixed $value): int
    {
        try {
            $minor = $this->money->fromDecimal((string) $value);
        } catch (\InvalidArgumentException) {
            $this->invalid();
        }
        if ($minor < 1) {
            $this->invalid();
        }

        return $minor;
    }

    private function fee(mixed $value): int
    {
        try {
            $minor = $this->money->fromDecimal((string) $value);
        } catch (\InvalidArgumentException) {
            $this->invalid();
        }
        if ($minor < 0) {
            $this->invalid();
        }

        return $minor;
    }

    /**
     * The fee for a withdrawal. Requesters cannot choose it; only an administrator
     * reviewing the payout may set it explicitly.
     */
    private function feeFor(User $actor, int $amount, mixed $requested, int $current = 0): int
    {
        if ($actor->role === 'admin') {
            return $requested === null ? $current : $this->fee($requested);
        }
        if ($requested !== null) {
            // Malformed input is still rejected as before; its value is not used.
            $this->fee($requested);
        }
        $bps = (int) config('wallet.withdrawal_fee_bps', 0);

        return (int) config('wallet.withdrawal_fee_fixed_minor', 0) + intdiv(($amount * $bps) + 9999, 10000);
    }

    /**
     * Refunded, reversed or disputed proceeds stay in the balance but cannot be withdrawn.
     */
    private function assertWithdrawable(Wallet $wallet, int $additionalMinor): void
    {
        $balance = $wallet->balance_minor ?? $this->money->fromDecimal($wallet->amount);
        if ($additionalMinor > 0 && $additionalMinor > $balance - FinancialHold::heldMinor((int) $wallet->id)) {
            throw ValidationException::withMessages(['amount' => SecurityMessage::get('insufficientBalance')]);
        }
    }

    public function create(User $actor, int $walletId, array $data): Withdraw
    {
        return DB::transaction(function () use ($actor, $walletId, $data) {
            $wallet = $this->lockedWallet($actor->id);
            abort_unless($wallet->id === $walletId, 403, SecurityMessage::get('denied'));
            $amount = $this->amount($data['amount']);
            $fee = $this->feeFor($actor, $amount, $data['fee'] ?? 0);
            if ($fee > $amount) {
                $this->invalid();
            }
            $this->assertCurrency($wallet, $data['currency'] ?? $wallet->currency);
            $this->assertWithdrawable($wallet, $amount);
            $withdrawal = Withdraw::create([
                ...$data, 'user_id' => $actor->id, 'amount' => $this->money->toDecimal($amount),
                'fee' => $this->money->toDecimal($fee), 'net_amount' => $this->money->toDecimal($amount - $fee),
                'currency' => $wallet->currency, 'status' => 'pending',
            ]);
            $this->move($wallet, $withdrawal, $actor, -$amount, $amount, 'reserve');

            return $withdrawal;
        }, 5);
    }

    public function update(User $actor, int $id, array $data): Withdraw
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $withdrawal = Withdraw::lockForUpdate()->findOrFail($id);
            $this->authorize($withdrawal, $actor);
            $state = $actor->role === 'admin' ? ($data['status'] ?? null) : null;
            unset($data['status']);
            if (! in_array($withdrawal->status, $actor->role === 'admin' ? ['pending', 'processing'] : ['pending'], true) || ! DB::table('wallet_reservation_movements')->where('withdrawal_id', $id)->where('action', 'reserve')->exists()) {
                $this->invalid();
            }
            $wallet = $this->lockedWallet($withdrawal->user_id);
            $this->assertCurrency($wallet, $data['currency'] ?? $withdrawal->currency);
            $old = $this->amount($withdrawal->amount);
            $amount = $this->amount($data['amount'] ?? $withdrawal->amount);
            $fee = $this->feeFor($actor, $amount, $data['fee'] ?? null, $this->fee($withdrawal->fee ?? 0));
            if ($fee > $amount) {
                $this->invalid();
            }
            $this->assertWithdrawable($wallet, $amount - $old);
            if ($amount !== $old) {
                $this->move($wallet, $withdrawal, $actor, $old - $amount, $amount - $old, 'adjust');
            }
            $withdrawal->update([...$data, 'amount' => $this->money->toDecimal($amount), 'fee' => $this->money->toDecimal($fee), 'net_amount' => $this->money->toDecimal($amount - $fee), 'currency' => $wallet->currency]);

            return $state && $state !== $withdrawal->status ? $this->transition($actor, $id, $state) : $withdrawal->fresh();
        }, 5);
    }

    public function transition(User $actor, int $id, string $state): Withdraw
    {
        return DB::transaction(function () use ($actor, $id, $state) {
            $withdrawal = Withdraw::lockForUpdate()->findOrFail($id);
            $this->authorize($withdrawal, $actor);
            abort_unless($state === 'rejected' || $actor->role === 'admin', 403, SecurityMessage::get('denied'));
            if ($withdrawal->status === $state) {
                return $withdrawal;
            }
            if (! in_array($withdrawal->status, $actor->role === 'admin' ? ['pending', 'processing'] : ['pending'], true) || ! in_array($state, ['processing', 'completed', 'rejected', 'cancelled'], true)) {
                $this->invalid();
            }
            $wallet = $this->lockedWallet($withdrawal->user_id);
            $this->assertCurrency($wallet, $withdrawal->currency);
            $amount = $this->amount($withdrawal->amount);
            // Unjournaled legacy reservations require reconciliation, never an automatic refund.
            if (! DB::table('wallet_reservation_movements')->where('withdrawal_id', $id)->where('action', 'reserve')->exists()) {
                $this->invalid();
            }
            $released = in_array($state, ['rejected', 'cancelled'], true);
            $this->move($wallet, $withdrawal, $actor, $released ? $amount : 0, $state === 'processing' ? 0 : -$amount, $state);
            $withdrawal->update(['status' => $state, 'approved_by' => $actor->role === 'admin' ? $actor->id : null, 'processed_at' => $state === 'processing' ? null : now()]);

            return $withdrawal->fresh();
        }, 5);
    }

    private function authorize(Withdraw $withdrawal, User $actor): void
    {
        abort_unless($actor->is_active && ($actor->role === 'admin' || $withdrawal->user_id === $actor->id), 403, SecurityMessage::get('denied'));
    }

    private function assertCurrency(Wallet $wallet, string $currency): void
    {
        if (strtoupper($currency) !== strtoupper($wallet->currency)) {
            throw ValidationException::withMessages(['currency' => SecurityMessage::get('currencyMismatch')]);
        }
    }

    private function move(Wallet $wallet, Withdraw $withdrawal, User $actor, int $balanceDelta, int $pendingDelta, string $action): void
    {
        $before = $wallet->balance_minor ?? $this->money->fromDecimal($wallet->amount);
        $pending = $this->money->fromDecimal((string) ($wallet->pending ?? 0));
        if ($balanceDelta < 0 && $before < -$balanceDelta) {
            throw ValidationException::withMessages(['amount' => SecurityMessage::get('insufficientBalance')]);
        }
        if ($pendingDelta < 0 && $pending < -$pendingDelta) {
            $this->invalid();
        }
        if (($balanceDelta > 0 && $before > PHP_INT_MAX - $balanceDelta) || ($pendingDelta > 0 && $pending > PHP_INT_MAX - $pendingDelta)) {
            $this->invalid();
        }
        $wallet->update(['balance_minor' => $before + $balanceDelta, 'amount' => $this->money->toDecimal($before + $balanceDelta), 'pending' => $this->money->toDecimal($pending + $pendingDelta)]);
        DB::table('wallet_reservation_movements')->insert([
            'wallet_id' => $wallet->id, 'withdrawal_id' => $withdrawal->id, 'actor_id' => $actor->id, 'action' => $action,
            'balance_before_minor' => $before, 'balance_after_minor' => $before + $balanceDelta,
            'pending_before_minor' => $pending, 'pending_after_minor' => $pending + $pendingDelta, 'created_at' => now(),
        ]);
    }
}
