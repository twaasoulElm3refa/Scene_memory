<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Models\GiftEarningEntry;
use App\Models\GiftTransaction;
use App\Models\GiftWithdrawal;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GiftEarningsService
{
    /** Append a compensating movement and update its locked projection together. Caller holds a transaction. */
    public function move(GiftEarningEntry $earning, string $key, string $type, array $deltas): void
    {
        if (DB::table('gift_earning_movements')->where('idempotency_key', $key)->exists()) {
            return;
        }
        $row = ['earning_entry_id' => $earning->id, 'idempotency_key' => $key, 'type' => $type, 'created_at' => now('UTC')];
        foreach ($deltas as $bucket => $delta) {
            if (! in_array($bucket, ['pending', 'available', 'reserved', 'settled', 'reversed', 'recovery'], true) || ! is_int($delta)) {
                throw new RuntimeException('Invalid earning movement.');
            }
            $column = $bucket.'_minor';
            $after = (int) $earning->$column + $delta;
            if ($after < 0) {
                throw new RuntimeException('Creator ledger cannot become negative.');
            }
            $earning->$column = $after;
            $row[$bucket.'_delta'] = $delta;
        }
        $allocated = $earning->pending_minor + $earning->available_minor + $earning->reserved_minor + $earning->settled_minor + $earning->reversed_minor;
        if ($allocated !== $earning->recipient_share_minor) {
            throw new RuntimeException('Creator entitlement allocation does not balance.');
        }
        DB::table('gift_earning_movements')->insert($row);
        $earning->status = $earning->recovery_minor ? 'review_required' : ($earning->pending_minor ? 'pending' : ($earning->reserved_minor ? 'reserved' : ($earning->available_minor ? 'available' : ($earning->settled_minor ? 'settled' : 'reversed'))));
        $earning->save();
        $earning->transaction()->update(['settlement_status' => $earning->status]);
    }

    public function release(int $limit = 500): int
    {
        $count = 0;
        GiftEarningEntry::where('pending_minor', '>', 0)->where('release_at', '<=', now('UTC'))
            ->whereHas('recipient', fn ($query) => $query->where('is_active', true))
            ->whereHas('transaction', fn ($query) => $query->whereIn('payment_status', ['paid', 'partially_refunded'])->where('disputed', false)->where('review_required', false))
            ->orderBy('id')->limit($limit)->pluck('id')->each(function ($id) use (&$count): void {
                $released = DB::transaction(function () use ($id): bool {
                    $candidate = GiftEarningEntry::findOrFail($id);
                    $user = User::whereKey($candidate->recipient_user_id)->lockForUpdate()->first();
                    $earning = GiftEarningEntry::lockForUpdate()->findOrFail($id);
                    $gift = $earning->transaction()->lockForUpdate()->firstOrFail();
                    if (! $user?->is_active || ! $earning->pending_minor || $earning->release_at->isFuture() || $gift->disputed || $gift->review_required || ! in_array($gift->payment_status, ['paid', 'partially_refunded'], true)) {
                        return false;
                    }
                    $this->move($earning, 'release:'.$gift->reference, 'released', ['pending' => -$earning->pending_minor, 'available' => $earning->pending_minor]);
                    app(VirtualGiftService::class)->audit($gift, 'released', 'release:'.$gift->reference);

                    return true;
                }, 5);
                $count += (int) $released;
            });

        return $count;
    }

    public function reverse(GiftTransaction $original, int $refundGross, string $key, string $kind = 'refunded'): GiftTransaction
    {
        return DB::transaction(function () use ($original, $refundGross, $key, $kind) {
            User::whereKey($original->recipient_user_id)->lockForUpdate()->first();
            $gift = GiftTransaction::lockForUpdate()->findOrFail($original->id);
            if (DB::table('gift_transaction_events')->where('event_key', $key)->exists()) {
                return $gift;
            }
            if ($gift->paid_at && $gift->refunded_gross_minor === $gift->gross_amount_minor) {
                app(VirtualGiftService::class)->audit($gift, 'reversal_already_covered', $key);

                return $gift;
            }
            if (! $gift->paid_at || $refundGross < 1 || $refundGross > $gift->gross_amount_minor - $gift->refunded_gross_minor) {
                throw new CommerceException('Reversal amount does not match the paid gift.');
            }
            $earning = GiftEarningEntry::where('gift_transaction_id', $gift->id)->lockForUpdate()->firstOrFail();
            // Cancel outstanding payout reservations before making compensating entries.
            $withdrawalIds = DB::table('gift_withdrawal_allocations')->where('earning_entry_id', $earning->id)->pluck('withdrawal_id');
            foreach (GiftWithdrawal::whereIn('id', $withdrawalIds)->whereIn('status', ['pending', 'approved'])->lockForUpdate()->get() as $withdrawal) {
                $this->changeWithdrawal($withdrawal, 'rejected', null, null);
            }
            $earning->refresh();
            $cumulative = $gift->refunded_gross_minor + $refundGross;
            $split = app(GiftCommission::class)->split($cumulative);
            $reversal = $split['recipient'] - $gift->reversed_earning_minor;
            $pending = min($earning->pending_minor, $reversal);
            $available = min($earning->available_minor, $reversal - $pending);
            $recovery = $reversal - $pending - $available;
            $this->move($earning, $key, $kind, ['pending' => -$pending, 'available' => -$available, 'settled' => -$recovery, 'reversed' => $reversal, 'recovery' => $recovery]);
            if ($gift->payment_method === 'wallet') {
                $wallet = Wallet::where('user_id', $gift->sender_user_id)->lockForUpdate()->firstOrFail();
                if ($wallet->currency !== $gift->currency) {
                    throw new CommerceException('Wallet currency changed; manual recovery is required.');
                }
                app(WalletLedgerService::class)->post($wallet, $gift->payment->order, null, 'credit', 'gift_refund', $refundGross, 'gift-refund:'.$key, $gift->reference);
            }
            $status = $cumulative === $gift->gross_amount_minor ? $kind : 'partially_refunded';
            $gift->update(['refunded_gross_minor' => $cumulative, 'reversed_commission_minor' => $split['commission'], 'reversed_earning_minor' => $split['recipient'], 'payment_status' => $status, 'delivery_status' => $cumulative === $gift->gross_amount_minor ? 'reversed' : 'delivered', 'review_required' => $gift->review_required || $recovery > 0]);
            $gift->payment->update(['status' => $status]);
            $gift->payment->order->update(['status' => $status]);
            app(VirtualGiftService::class)->audit($gift, $kind, $key, ['gross_minor' => $refundGross, 'recovery_minor' => $recovery]);

            return $gift->fresh();
        }, 5);
    }

    public function withdraw(int $userId, array $input): GiftWithdrawal
    {
        return DB::transaction(function () use ($userId, $input) {
            $user = User::lockForUpdate()->findOrFail($userId);
            if (! $user->is_active) {
                throw new CommerceException('Inactive account.', 403);
            }
            $key = hash('sha256', 'gift-withdrawal|'.$userId.'|'.$input['idempotency_key']);
            if ($existing = GiftWithdrawal::where('idempotency_key', $key)->first()) {
                if ($existing->amount_minor !== $input['amount_minor'] || $existing->currency !== $input['currency'] || $existing->payment_details !== $input['payment_details']) {
                    throw new CommerceException('Withdrawal key has different details.', 409);
                }

                return $existing;
            }
            $entries = GiftEarningEntry::where('recipient_user_id', $userId)->where('currency', $input['currency'])->with('transaction')->orderBy('id')->lockForUpdate()->get();
            if ($entries->sum('recovery_minor') > 0) {
                throw new CommerceException('Outstanding recovery requires administrator review.');
            }
            $eligible = $entries->filter(fn ($entry) => $entry->available_minor > 0 && ! $entry->transaction->disputed && ! $entry->transaction->review_required);
            if ($eligible->sum('available_minor') < $input['amount_minor']) {
                throw new CommerceException('Insufficient available earnings.');
            }
            $withdrawal = GiftWithdrawal::create(['reference' => (string) Str::uuid(), 'user_id' => $userId, 'idempotency_key' => $key, 'amount_minor' => $input['amount_minor'], 'currency' => $input['currency'], 'payment_details' => $input['payment_details']]);
            $remaining = $input['amount_minor'];
            foreach ($eligible as $entry) {
                $take = min($remaining, $entry->available_minor);
                if ($take < 1) {
                    break;
                }
                DB::table('gift_withdrawal_allocations')->insert(['withdrawal_id' => $withdrawal->id, 'earning_entry_id' => $entry->id, 'amount_minor' => $take]);
                $this->move($entry, 'reserve:'.$withdrawal->reference.':'.$entry->id, 'reserved', ['available' => -$take, 'reserved' => $take]);
                app(VirtualGiftService::class)->audit($entry->transaction, 'withdrawal_reserved', 'reserve:'.$withdrawal->reference.':'.$entry->id, ['withdrawal' => $withdrawal->reference, 'amount_minor' => $take], $userId);
                $remaining -= $take;
            }

            return $withdrawal;
        }, 5);
    }

    public function changeWithdrawal(GiftWithdrawal $original, string $status, ?int $actor, ?string $payoutReference): GiftWithdrawal
    {
        return DB::transaction(function () use ($original, $status, $actor, $payoutReference) {
            User::whereKey($original->user_id)->lockForUpdate()->firstOrFail();
            $withdrawal = GiftWithdrawal::lockForUpdate()->findOrFail($original->id);
            if ($withdrawal->status === $status) {
                if ($status === 'paid' && $withdrawal->payout_reference !== $payoutReference) {
                    throw new CommerceException('Payout reference cannot be changed.', 409);
                }

                return $withdrawal;
            }
            if ($status === 'cancelled' && $withdrawal->status !== 'pending') {
                throw new CommerceException('Only pending withdrawals can be cancelled.', 409);
            }
            if (! in_array($withdrawal->status, ['pending', 'approved'], true) || ! in_array($status, ['approved', 'paid', 'rejected', 'cancelled'], true) || ($status === 'paid' && $withdrawal->status !== 'approved')) {
                throw new CommerceException('Invalid withdrawal transition.', 409);
            }
            $allocations = DB::table('gift_withdrawal_allocations')->where('withdrawal_id', $withdrawal->id)->get();
            foreach ($allocations as $allocation) {
                $entry = GiftEarningEntry::lockForUpdate()->findOrFail($allocation->earning_entry_id);
                $gift = $entry->transaction()->lockForUpdate()->firstOrFail();
                if (in_array($status, ['approved', 'paid'], true) && ($gift->disputed || $gift->review_required || $entry->recovery_minor > 0)) {
                    throw new CommerceException('Gift earnings require review.');
                }
                if ($status === 'paid') {
                    if (! $payoutReference) {
                        throw new CommerceException('An external payout reference is required.');
                    }
                    $this->move($entry, 'paid:'.$withdrawal->reference.':'.$entry->id, 'settled', ['reserved' => -(int) $allocation->amount_minor, 'settled' => (int) $allocation->amount_minor]);
                    $gift->update(['settled_at' => now('UTC')]);
                } elseif (in_array($status, ['rejected', 'cancelled'], true)) {
                    $this->move($entry, 'unreserve:'.$withdrawal->reference.':'.$entry->id, 'unreserved', ['reserved' => -(int) $allocation->amount_minor, 'available' => (int) $allocation->amount_minor]);
                }
                app(VirtualGiftService::class)->audit($gift, 'withdrawal_'.$status, 'withdrawal-'.$status.':'.$withdrawal->reference.':'.$entry->id, ['withdrawal' => $withdrawal->reference, 'payout_reference' => $payoutReference], $actor);
            }
            $withdrawal->update(['status' => $status, 'approved_by' => $actor, 'payout_reference' => $status === 'paid' ? $payoutReference : null, 'paid_at' => $status === 'paid' ? now('UTC') : null]);

            return $withdrawal->fresh();
        }, 5);
    }
}
