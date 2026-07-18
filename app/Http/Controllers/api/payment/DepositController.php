<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;
use App\Services\PayPalWalletServices;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DepositController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PayPalWalletServices $paypal,
        private readonly PurchaseRepositoryInterface $purchaseRepository
    ) {}

    // POST /api/v1/deposit/pay
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $validated['user_id'] = $request->user()?->id;

        try {
            ['order' => $order, 'approval_url' => $url] = $this->paypal->pay($validated);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'approval_url' => $url,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/wallet/success?token=xxx
    public function success(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token missing.',
            ], 400);
        }

        try {
            $result = $this->paypal->success($token);

            $orderId = $result['order_id'] ?? $result['order']?->id;

            if (! $orderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order ID missing.',
                ], 500);
            }

            return redirect(rtrim((string) config('app.frontend_url'), '/').'/en/Deposit/waiting?'.http_build_query([
                'order_id' => $orderId,
                'status' => $result['order']->status,
            ]));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/wallet/cancel
    public function cancel(): JsonResponse
    {
        try {
            return response()->json($this->paypal->cancel());
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/wallet/order-status/{id}
    public function orderStatus(Request $request, $id): JsonResponse
    {
        $order = $this->purchaseRepository->findById((int) $id);

        if (! $order) {
            return response()->json([
                'status' => 'not_found',
            ], 404);
        }

        return response()->json([
            'status' => $order->status,
            'amount' => $order->amount,
            'order_id' => $order->id,
        ]);
    }

    public function clearUserProfileCache($id): void
    {
        $cacheKey = 'user_profile_'.$id;
        Cache::tags(['user_profile', 'user_'.$id])->forget($cacheKey);
    }
}
