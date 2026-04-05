<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Models\cart;
use App\Models\cartItems;
use App\Services\PayPalServices;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Http\Controllers\concerns\ApiResponse;
use App\Models\purchase_items;
use Illuminate\Support\Facades\Cache;

// ══════════════════════════════════════════════════════════════════════════════
// PaymentController  —  User-facing endpoints
// ══════════════════════════════════════════════════════════════════════════════

class PaymentController extends Controller
{
    use ApiResponse;

    private function clearCartCache($userId)
    {
        Cache::tags(['cart', "user_{$userId}"])->flush();
        Cache::tags('user_profile')->flush();
    }

    public function __construct(protected PayPalServices $paypal) {}

    public function pay(Request $request): JsonResponse
    {

        // Validation
        $validated = $request->validate([
            'amount'          => 'required|numeric|min:0.01',
            'description'     => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $validated['user_id'] = $request->user()?->id;

        try {
            ['order' => $order, 'approval_url' => $url] = $this->paypal->pay($validated);


            return response()->json([
                'success'      => true,
                'order_id'     => $order->id,
                'approval_url' => $url,
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── GET /api/paypal/success?token=xxx ─────────────────────────────────────
    /**
     * PayPal redirect بعد موافقة اليوزر.
     * مش final — الـ source of truth هو الـ webhook.
     */
    public function success(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {

            return response()->json(['success' => false, 'message' => 'Token missing.'], 400);
        }

        try {
            $result = $this->paypal->success($token);
            $user = $result['order']->user_id;
            $purchase=$result['order']->id;
            $cart = cart::where('user_id', $user)->first();
            if (!$cart) {
                return $this->error('Cart not Found',404);
            }
            $items = cartItems::where("cart_id", $cart->id)->get();

            foreach ($items as $item) {
                purchase_items::create([
                    "purchase_id" => $purchase,
                    "image_id" => $item->image_id,
                    "price" => $item->price,
                ]);
            }

            cartItems::where('cart_id', $cart->id)->delete();

            $this->clearCartCache($user);

            return redirect('/en/downloads');


        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── GET /api/paypal/cancel ────────────────────────────────────────────────
    public function cancel(): JsonResponse
    {

        try {
            $result = $this->paypal->cancel();

            return response()->json($result);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
