<?php

namespace App\Http\Controllers\api\admin;

use App\Exceptions\CommerceException;
use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\GiftEarningEntry;
use App\Models\GiftTransaction;
use App\Models\GiftWithdrawal;
use App\Models\User;
use App\Models\VirtualGift;
use App\Services\GiftEarningsService;
use App\Services\PayPalServices;
use App\Services\VirtualGiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VirtualGiftAdminController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success(VirtualGift::orderBy('sort_order')->orderBy('id')->get())->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, ?int $id = null)
    {
        $gift = $id ? VirtualGift::findOrFail($id) : new VirtualGift;
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => ['required_without:price_minor', 'regex:/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/'],
            'price_minor' => 'required_without:price|integer|min:1|max:99999999',
            'image' => [$id ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $minor = isset($data['price'])
            ? (int) explode('.', $data['price'])[0] * 100 + (int) str_pad(explode('.', $data['price'])[1] ?? '0', 2, '0')
            : (int) $data['price_minor'];
        if ($minor < 1 || $minor > 99999999) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price' => 'Enter a USD price between 0.01 and 999999.99.']);
        }

        $newImage = null;
        try {
            if ($request->hasFile('image')) {
                $newImage = $request->file('image')->store('virtual-gifts', 'public');
                if (! $newImage) throw new \RuntimeException('Gift image could not be stored.');
            }
            $gift->name = $data['name'];
            $gift->price_minor = $minor;
            if (! $id) {
                $gift->slug = Str::limit(Str::slug($data['name']) ?: 'gift', 72, '').'-'.Str::lower(Str::random(12));
                $gift->currency = 'USD';
                $gift->is_active = true;
                $gift->sort_order = (int) VirtualGift::max('sort_order') + 1;
            }
            if ($newImage) {
                $gift->image_path = $newImage;
            }
            $gift->save();
        } catch (\Throwable $exception) {
            if ($newImage) Storage::disk('public')->delete($newImage);
            Log::error('gift_catalog_save_failed', ['exception' => $exception::class]);

            return $this->error('Gift could not be saved. Please try again or contact support.', 500);
        }

        return $this->success($gift->fresh());
    }

    public function activate(int $id)
    {
        $gift = VirtualGift::findOrFail($id);
        $gift->update(['is_active' => true]);

        return $this->success($gift);
    }

    public function deactivate(int $id)
    {
        $gift = VirtualGift::findOrFail($id);
        $gift->update(['is_active' => false]);

        return $this->success($gift);
    }

    public function transactions(Request $request)
    {
        $data = $request->validate(['status' => 'sometimes|in:pending,paid,partially_refunded,refunded,reversed,failed,cancelled', 'payment_method' => 'sometimes|in:wallet,paypal', 'recipient_user_id' => 'sometimes|integer|min:1', 'review_required' => 'sometimes|boolean', 'page' => 'sometimes|integer|min:1']);
        $query = GiftTransaction::with(['sender:id,name', 'recipient:id,name', 'earning']);
        foreach (['payment_method', 'recipient_user_id', 'review_required'] as $filter) {
            if (isset($data[$filter])) {
                $query->where($filter, $data[$filter]);
            }
        }
        if (isset($data['status'])) {
            $query->where('payment_status', $data['status']);
        }

        return $this->success($query->latest('id')->paginate(30));
    }

    public function audit(string $reference)
    {
        $gift = GiftTransaction::where('reference', $reference)->firstOrFail();
        $earning = $gift->earning;

        return $this->success(['events' => DB::table('gift_transaction_events')->where('gift_transaction_id', $gift->id)->orderBy('id')->get(), 'movements' => $earning ? DB::table('gift_earning_movements')->where('earning_entry_id', $earning->id)->orderBy('id')->get() : []]);
    }

    public function action(Request $request, string $reference, PayPalServices $paypal, VirtualGiftService $service, GiftEarningsService $earnings)
    {
        $data = $request->validate(['action' => 'required|in:reconcile,refund,reconcile_refund,clear_review,recover', 'proof_reference' => 'required_if:action,recover,clear_review,reconcile_refund|string|max:160', 'amount_minor' => 'required_if:action,recover|integer|min:1|max:99999999']);
        if ($data['action'] === 'reconcile_refund') {
            $request->validate(['proof_reference' => 'required|regex:/^[A-Za-z0-9_-]+$/|max:64']);
        }
        $gift = GiftTransaction::where('reference', $reference)->firstOrFail();
        try {
            if ($data['action'] === 'refund') {
                return $this->success($paypal->refundGift($gift, $request->user()->id));
            }
            if ($data['action'] === 'reconcile_refund') {
                return $this->success($paypal->reconcileGiftRefund($gift, $data['proof_reference']));
            }
            if ($data['action'] === 'reconcile') {
                $paypal->reconcileGift($gift->payment);

                return $this->success($gift->fresh());
            }

            return DB::transaction(function () use ($gift, $data, $request, $service, $earnings) {
                User::whereKey($gift->recipient_user_id)->lockForUpdate()->firstOrFail();
                $gift = GiftTransaction::lockForUpdate()->findOrFail($gift->id);
                $entry = GiftEarningEntry::where('gift_transaction_id', $gift->id)->lockForUpdate()->firstOrFail();
                if ($data['action'] === 'recover') {
                    $amount = (int) $data['amount_minor'];
                    $key = 'recovered:'.$gift->reference.':'.hash('sha256', $data['proof_reference']);
                    if (! DB::table('gift_earning_movements')->where('idempotency_key', $key)->exists()) {
                        if ($amount > $entry->recovery_minor) {
                            throw new CommerceException('Recovery exceeds outstanding amount.');
                        }
                        $earnings->move($entry, $key, 'recovered', ['recovery' => -$amount]);
                        $service->audit($gift, 'recovered', $key, ['amount_minor' => $amount, 'proof_reference' => $data['proof_reference']], $request->user()->id);
                    }
                } else {
                    if ($gift->disputed || $entry->recovery_minor || ($gift->partial_refund_marker && ! $gift->refunded_gross_minor) || ($gift->refund_request_id && $gift->refunded_gross_minor < $gift->gross_amount_minor)) {
                        throw new CommerceException('Resolve disputes, refunds and recovery before clearing review.');
                    }
                    $gift->update(['review_required' => false]);
                    $service->audit($gift, 'review_cleared', 'review-cleared:'.$gift->reference.':'.hash('sha256', $data['proof_reference']), ['proof_reference' => $data['proof_reference']], $request->user()->id);
                }

                return $this->success($gift->fresh());
            }, 5);
        } catch (CommerceException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        } catch (\Throwable $exception) {
            Log::warning('gift_admin_action_failed', ['gift' => $gift->reference, 'exception' => $exception::class]);

            return $this->error('Provider confirmation is unavailable; retry reconciliation.', 502);
        }
    }

    public function report()
    {
        $rows = GiftTransaction::whereNotNull('paid_at')->selectRaw("currency, SUM(gross_amount_minor) gross_payments_minor, SUM(gross_amount_minor-refunded_gross_minor) gross_minor, SUM(platform_commission_minor-reversed_commission_minor) commission_minor, SUM(recipient_earning_minor-reversed_earning_minor) recipient_minor, SUM(CASE WHEN payment_method='wallet' THEN gross_amount_minor ELSE 0 END) wallet_volume_minor, SUM(CASE WHEN payment_method='paypal' THEN gross_amount_minor ELSE 0 END) paypal_volume_minor, SUM(CASE WHEN processing_fee_status='confirmed' AND processing_fee_currency=currency THEN payment_processing_fee_minor ELSE 0 END) processing_fee_minor, SUM(CASE WHEN payment_method='paypal' AND (processing_fee_status!='confirmed' OR processing_fee_currency!=currency) THEN 1 ELSE 0 END) unknown_fee_count, SUM(refunded_gross_minor) refunded_minor")->groupBy('currency')->get();
        $rows->each(function ($row) {
            $row->net_is_provisional = $row->unknown_fee_count > 0;
            $row->platform_net_minor = $row->net_is_provisional ? null : (int) $row->commission_minor - (int) $row->processing_fee_minor;
        });

        return $this->success([
            'financials' => $rows,
            'processing_costs' => GiftTransaction::where('processing_fee_status', 'confirmed')->selectRaw('processing_fee_currency currency, processing_fee_precision fee_decimals, SUM(payment_processing_fee_minor) amount_minor')->groupBy('processing_fee_currency', 'processing_fee_precision')->get(),
            'balances' => GiftEarningEntry::selectRaw('currency, SUM(pending_minor) pending_minor, SUM(available_minor) available_minor, SUM(reserved_minor) reserved_minor, SUM(settled_minor) settled_minor, SUM(reversed_minor) reversed_minor, SUM(recovery_minor) recovery_minor')->groupBy('currency')->get(),
            'payouts' => GiftWithdrawal::selectRaw('currency, status, SUM(amount_minor) amount_minor')->groupBy('currency', 'status')->get(),
            'payment_counts' => GiftTransaction::selectRaw('payment_status, payment_method, COUNT(*) count')->groupBy('payment_status', 'payment_method')->get(),
            'popular_gifts' => GiftTransaction::whereNotNull('paid_at')->where('delivery_status', 'delivered')->selectRaw('gift_id, currency, SUM(quantity) quantity')->groupBy('gift_id', 'currency')->orderByDesc('quantity')->limit(20)->get(),
            'supported_events' => GiftTransaction::whereNotNull('paid_at')->selectRaw('event_id, currency, SUM(gross_amount_minor-refunded_gross_minor) gross_minor')->groupBy('event_id', 'currency')->orderByDesc('gross_minor')->limit(20)->get(),
            'recipients' => GiftEarningEntry::selectRaw('recipient_user_id, currency, SUM(recipient_share_minor-reversed_minor) entitlement_minor, SUM(pending_minor) pending_minor, SUM(available_minor) available_minor, SUM(reserved_minor) reserved_minor, SUM(settled_minor) settled_minor, SUM(recovery_minor) recovery_minor')->groupBy('recipient_user_id', 'currency')->paginate(30),
        ]);
    }

    public function withdrawals(Request $request)
    {
        $data = $request->validate(['status' => 'sometimes|in:pending,approved,processing,failed,rejected,cancelled,paid', 'page' => 'sometimes|integer|min:1']);
        $query = GiftWithdrawal::with('user:id,name')->latest('id');
        if (isset($data['status'])) $query->where('status', $data['status']);
        $page = $query->paginate(30);
        $page->through(function ($withdrawal) {
            $withdrawal->payment_method = $withdrawal->payment_details['method'] ?? null;
            $withdrawal->destination_masked = '••••';
            $withdrawal->setHidden(['payment_details']);

            return $withdrawal;
        });

        return $this->success($page)->header('Cache-Control', 'no-store');
    }

    public function withdrawalDetail(string $reference)
    {
        $withdrawal = GiftWithdrawal::with('user:id,name,email')->where('reference', $reference)->firstOrFail();
        $allocations = DB::table('gift_withdrawal_allocations as allocation')
            ->join('gift_earning_entries as earning', 'earning.id', '=', 'allocation.earning_entry_id')
            ->join('gift_transactions as gift', 'gift.id', '=', 'earning.gift_transaction_id')
            ->where('allocation.withdrawal_id', $withdrawal->id)
            ->select('allocation.amount_minor', 'earning.id as earning_entry_id', 'earning.available_minor', 'earning.reserved_minor', 'earning.recovery_minor', 'gift.reference as gift_reference', 'gift.payment_status', 'gift.delivery_status', 'gift.disputed', 'gift.review_required')
            ->get();
        $balance = GiftEarningEntry::where('recipient_user_id', $withdrawal->user_id)->where('currency', $withdrawal->currency)
            ->selectRaw('SUM(pending_minor) pending_minor, SUM(available_minor) available_minor, SUM(reserved_minor) reserved_minor, SUM(recovery_minor) recovery_minor')->first();

        return $this->success(['withdrawal' => $withdrawal, 'allocations' => $allocations, 'balance' => $balance])->header('Cache-Control', 'no-store');
    }

    public function withdrawal(Request $request, string $reference, GiftEarningsService $earnings)
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected,processing,failed,paid', 'payout_reference' => 'required_if:status,paid|string|max:160', 'reason' => 'required_if:status,rejected,failed|nullable|string|min:3|max:2000']);
        try {
            return $this->success($earnings->changeWithdrawal(GiftWithdrawal::where('reference', $reference)->firstOrFail(), $data['status'], $request->user()->id, $data['payout_reference'] ?? null, $data['reason'] ?? null));
        } catch (CommerceException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }
    }
}
