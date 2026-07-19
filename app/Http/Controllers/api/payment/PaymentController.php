<?php

namespace App\Http\Controllers\api\payment;

use App\Exceptions\CommerceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\WalletPurchaseRequest;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\Wallet;
use App\Services\CheckoutCartSnapshot;
use App\Services\MinorMoney;
use App\Services\PayPalServices;
use App\Services\PaymentFinalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PayPalServices $paypal,
        private readonly CheckoutCartSnapshot $orderBuilder,
        private readonly PaymentFinalizer $finalizer,
        private readonly MinorMoney $money,
    ) {}

    public function pay(CheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        try {
            ['order' => $order, 'approval_url' => $url] = $this->paypal->pay($data);
            return response()->json(['order_id' => $order->id, 'approval_url' => $url]);
        } catch (CommerceException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            Log::error('payment_order_creation_failed', ['user_id' => $request->user()->id, 'exception' => $exception::class]);
            return response()->json(['message' => 'Unable to create the payment order.'], 502);
        }
    }

    public function payWallet(WalletPurchaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        try {
            $order = DB::transaction(function () use ($data) {
                ['payment' => $payment] = $this->orderBuilder->create($data, 'wallet');
                return $this->finalizer->finalizeWalletPurchase($payment);
            }, 5);
            $wallet = Wallet::query()->where('user_id', $request->user()->id)->firstOrFail();

            return response()->json([
                'order_id' => $order->id,
                'status' => 'completed',
                'amount' => $order->amount,
                'currency' => $order->currency,
                'payment_method' => 'wallet',
                'balance' => $this->money->toDecimal((int) $wallet->balance_minor),
            ]);
        } catch (CommerceException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            $status = $exception->getMessage() === 'Insufficient wallet balance.' ? 422 : 500;
            Log::warning('wallet_purchase_failed', ['user_id' => $request->user()->id, 'exception' => $exception::class]);
            return response()->json(['message' => $status === 422 ? $exception->getMessage() : 'Wallet payment could not be completed.'], $status);
        }
    }

    public function success(Request $request)
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            return response()->json(['message' => 'Token missing.'], 400);
        }

        try {
            $result = $this->paypal->success($token);
            return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/waiting?'.http_build_query([
                'order_id' => $result['order_id'],
            ]));
        } catch (Throwable) {
            return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/failed');
        }
    }

    public function cancel()
    {
        return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/failed?cancelled=1');
    }

    public function orderStatus(Request $request, int $id): JsonResponse
    {
        $order = Purchases::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->where('type', 'checkout')
            ->first();
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->first();

        return response()->json([
            'order_id' => $order->id,
            'status' => $payment?->status ?? $order->status,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'payment_method' => $payment?->method ?? $order->payment_method,
            'paid_at' => $payment?->paid_at?->toIso8601String(),
            'fulfillment_status' => $payment?->purchase_granted ? 'fulfilled' : 'pending',
        ]);
    }
}
