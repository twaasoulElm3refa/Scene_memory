<?php

namespace App\Http\Controllers\api\gifts;

use App\Exceptions\CommerceException;
use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendVirtualGiftRequest;
use App\Models\Comments;
use App\Models\GiftEarningEntry;
use App\Models\GiftTransaction;
use App\Models\GiftWithdrawal;
use App\Models\VirtualGift;
use App\Models\Wallet;
use App\Services\GiftEarningsService;
use App\Services\MinorMoney;
use App\Services\PayPalServices;
use App\Services\VirtualGiftService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class GiftController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly VirtualGiftService $gifts, private readonly PayPalServices $paypal) {}

    public function catalog()
    {
        if (! config('virtual_gifts.enabled', true)) {
            return $this->success([]);
        }

        return $this->success(VirtualGift::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get());
    }

    public function wallet(SendVirtualGiftRequest $request)
    {
        return $this->attempt(fn () => $this->serialize($this->gifts->sendWallet($request->user()->id, $request->validated())));
    }

    public function createOrder(SendVirtualGiftRequest $request)
    {
        return $this->attempt(function () use ($request) {
            $gift = $this->gifts->create($request->user()->id, $request->validated(), 'paypal');
            $result = $this->paypal->createGiftOrder($gift);

            return ['transaction' => $this->serialize($result['transaction']), 'approval_url' => $result['approval_url']];
        });
    }

    public function capture(Request $request)
    {
        $data = $request->validate(['reference' => 'required|uuid']);
        $gift = GiftTransaction::where('sender_user_id', $request->user()->id)->where('reference', $data['reference'])->firstOrFail();

        return $this->attempt(function () use ($gift) {
            $this->paypal->reconcileGift($gift->payment);

            return $this->serialize($gift->fresh());
        });
    }

    public function cancel(Request $request, string $reference)
    {
        $gift = GiftTransaction::where('sender_user_id', $request->user()->id)->where('reference', $reference)->firstOrFail();
        $this->paypal->giftState($gift, 'cancelled');

        return $this->success($this->serialize($gift->fresh()));
    }

    public function status(Request $request, string $reference)
    {
        $gift = GiftTransaction::where('reference', $reference)->where(fn ($q) => $q->where('sender_user_id', $request->user()->id)->orWhere('recipient_user_id', $request->user()->id))->firstOrFail();

        return $this->success($this->serialize($gift))->header('Cache-Control', 'no-store');
    }

    public function history(Request $request)
    {
        $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $column = $request->routeIs('gifts.received') ? 'recipient_user_id' : 'sender_user_id';
        $page = GiftTransaction::where($column, $request->user()->id)->with(['sender:id,name,image', 'recipient:id,name,image'])->latest('id')->paginate((int) $request->input('per_page', 20));
        $page->through(fn ($gift) => $this->serialize($gift));

        return $this->success($page);
    }

    public function eventSummary(int $event)
    {
        $this->gifts->visibleEvent($event);

        return $this->success($this->summary(GiftTransaction::where('event_id', $event)->whereNull('comment_id')));
    }

    public function commentSummary(int $comment)
    {
        $comment = Comments::findOrFail($comment);
        $this->gifts->visibleEvent($comment->event_id);

        return $this->success($this->summary(GiftTransaction::where('comment_id', $comment->id)));
    }

    public function earnings(Request $request)
    {
        $balances = GiftEarningEntry::where('recipient_user_id', $request->user()->id)->selectRaw('currency, SUM(pending_minor) pending_minor, SUM(available_minor) available_minor, SUM(reserved_minor) reserved_minor, SUM(settled_minor) settled_minor, SUM(reversed_minor) reversed_minor, SUM(recovery_minor) recovery_minor')->groupBy('currency')->get();
        $eligible = GiftEarningEntry::where('recipient_user_id', $request->user()->id)
            ->whereHas('transaction', fn ($query) => $query->where('disputed', false)->where('review_required', false))
            ->selectRaw('currency, SUM(available_minor) available_minor')->groupBy('currency')->pluck('available_minor', 'currency');
        $balances->each(function ($balance) use ($eligible, $request) {
            $balance->withdrawable_minor = $balance->recovery_minor > 0 || ! $request->user()->is_active ? 0 : (int) ($eligible[$balance->currency] ?? 0);
        });
        $wallet = Wallet::where('user_id', $request->user()->id)->first();
        $legacyBalance = $wallet ? app(MinorMoney::class)->fromDecimal((string) ($wallet->amount ?? '0')) : null;

        return $this->success(['balances' => $balances, 'wallet' => $wallet ? ['balance_minor' => $wallet->balance_minor ?? $legacyBalance, 'currency' => $wallet->currency, 'is_consistent' => $wallet->balance_minor === null || $wallet->balance_minor === $legacyBalance] : null, 'holding_days' => config('virtual_gifts.holding_days')]);
    }

    public function withdrawals(Request $request)
    {
        $page = GiftWithdrawal::where('user_id', $request->user()->id)->latest('id')->paginate(20);

        return $this->success($page);
    }

    public function withdraw(Request $request, GiftEarningsService $earnings)
    {
        $data = $request->validate(['amount_minor' => 'required|integer|min:1|max:99999999', 'currency' => ['required', Rule::in(config('virtual_gifts.currencies'))], 'payment_details' => 'required|array:method,destination', 'payment_details.method' => 'required|string|max:100', 'payment_details.destination' => 'required|string|max:200', 'idempotency_key' => 'required|string|min:16|max:100']);
        $data['amount_minor'] = (int) $data['amount_minor'];

        return $this->attempt(fn () => $earnings->withdraw($request->user()->id, $data));
    }

    public function cancelWithdrawal(Request $request, string $reference, GiftEarningsService $earnings)
    {
        $withdrawal = GiftWithdrawal::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
        if ($withdrawal->status === 'cancelled') {
            return $this->success($withdrawal);
        }
        if ($withdrawal->status !== 'pending') {
            return $this->error('Only pending withdrawals can be cancelled.', 409);
        }

        return $this->attempt(fn () => $earnings->changeWithdrawal($withdrawal, 'cancelled', $request->user()->id, null));
    }

    public function paypalReturn(Request $request)
    {
        // A redirect is navigation only. The authenticated client must request capture/status.
        $data = $request->validate(['reference' => 'required|uuid', 'cancelled' => 'sometimes|in:1']);
        $gift = GiftTransaction::where('reference', $data['reference'])->firstOrFail();
        $locale = $gift->gift_snapshot['checkout_locale'] ?? 'en';
        if (! in_array($locale, ['ar', 'de', 'en', 'es', 'fa', 'fr', 'hi', 'it', 'ja', 'ru', 'tr', 'ur', 'zh'], true)) {
            $locale = 'en';
        }

        return redirect(rtrim(config('app.frontend_url') ?: config('app.url'), '/').'/'.$locale.'/gifts?'.http_build_query(['reference' => $data['reference'], 'cancelled' => $data['cancelled'] ?? null]));
    }

    private function summary($query): array
    {
        return $query->where('delivery_status', 'delivered')->whereIn('payment_status', ['paid', 'partially_refunded'])->selectRaw('gift_id, currency, SUM(quantity) quantity, SUM(gross_amount_minor - refunded_gross_minor) gross_minor')->groupBy('gift_id', 'currency')->get()->toArray();
    }

    public function serialize(GiftTransaction $gift): array
    {
        $gift->loadMissing(['sender:id,name,image', 'recipient:id,name,image']);

        return $gift->only(['reference', 'event_id', 'comment_id', 'target_type', 'gift_snapshot', 'quantity', 'unit_price_minor', 'gross_amount_minor', 'platform_commission_minor', 'recipient_earning_minor', 'currency', 'payment_method', 'payment_status', 'delivery_status', 'settlement_status', 'paid_at', 'delivered_at', 'release_at', 'refunded_gross_minor', 'created_at']) + ['sender' => $gift->sender?->only(['id', 'name']), 'recipient' => $gift->recipient?->only(['id', 'name']), 'can_capture' => $gift->sender_user_id === request()->user()?->id && $gift->payment_method === 'paypal'];
    }

    private function attempt(callable $work)
    {
        try {
            return $this->success($work());
        } catch (CommerceException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('gift_operation_failed', ['exception' => $exception::class]);

            return $this->error('Gift payment could not be verified. Retry using the same request key.', 502);
        }
    }
}
