<?php

namespace App\Http\Controllers\api\payment;

use App\Exceptions\CommerceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WalletDepositRequest;
use App\Models\Payment;
use App\Models\Purchases;
use App\Services\PayPalWalletServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DepositController extends Controller
{
    public function __construct(private readonly PayPalWalletServices $paypal) {}

    public function create(WalletDepositRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        try {
            ['order' => $order, 'approval_url' => $url] = $this->paypal->pay($data);
            return response()->json(['order_id' => $order->id, 'approval_url' => $url]);
        } catch (CommerceException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            Log::error('wallet_deposit_order_creation_failed', ['user_id' => $request->user()->id, 'exception' => $exception::class]);
            return response()->json(['message' => 'Unable to create the wallet deposit.'], 502);
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
            return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/Deposit/waiting?'.http_build_query([
                'order_id' => $result['order_id'],
            ]));
        } catch (Throwable) {
            return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/deposit/failed');
        }
    }

    public function cancel()
    {
        return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/deposit/failed?cancelled=1');
    }

    public function orderStatus(Request $request, int $id): JsonResponse
    {
        $order = Purchases::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->where('type', 'wallet_deposit')
            ->first();
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('operation', 'wallet_deposit')
            ->latest('id')
            ->first();

        return response()->json([
            'order_id' => $order->id,
            'status' => $payment?->status ?? $order->status,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'payment_method' => 'paypal',
            'paid_at' => $payment?->paid_at?->toIso8601String(),
            'fulfillment_status' => $payment?->wallet_credited ? 'wallet_credited' : 'pending',
        ]);
    }
}
