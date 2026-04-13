<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Models\cart;
use App\Models\cartItems;
use App\Services\PayPalServices;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use App\Http\Controllers\concerns\ApiResponse;
use App\Mail\PaymentSuccessMail;
use App\Models\purchase_items;
use App\Models\purchases;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
            $user     = $result['order']->user_id;
            $purchase = $result['order']->id;

            $cart = cart::where('user_id', $user)->first();
            if (!$cart) {
                return $this->error('Cart not Found', 404);
            }

            $items = cartItems::where("cart_id", $cart->id)->get();
            foreach ($items as $item) {
                purchase_items::create([
                    "purchase_id" => $purchase,
                    "image_id"    => $item->image_id,
                    "price"       => $item->price,
                ]);
            }

            cartItems::where('cart_id', $cart->id)->delete();
            $this->clearCartCache($user);

            return redirect('/en/waiting?order_id=' . $purchase);

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

    public function orderStatus(Request $request, $id): JsonResponse
    {
        $order = \App\Models\purchases::find($id);

        if (!$order) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status'   => $order->status,
            'amount'   => $order->amount,
            'order_id' => $order->id,
        ]);
    }


    public function payWallet(): JsonResponse
    {
        return DB::transaction(function () {
            $user = auth()->user();
            $wallet = \App\Models\Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wallet not found',
                ], 404);
            }
            $cart = cart::where('user_id', $user->id)->first();
            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart not found',
                ], 404);
            }
            $items = cartItems::where("cart_id", $cart->id)->get();
            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart is empty',
                ], 400);
            }
            $total = $items->sum('price');
            if ($wallet->amount < $total) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough money in wallet',
                ], 422);
            }
            $wallet->decrement('amount', $total);
            $purchase = purchases::create([
                "user_id" => $user->id,
                'type'    => 'wallet',
                'amount'  => $total,
                'status'  => 'completed',
                'idempotency_key' => md5($user->id . now() .'|' . $total . '|' . now()->format('Ymd')),
                'currency' => 'USD',
                'description' => 'Wallet payment',
                'payer_email' => $user->email,
                'paid_at' => now(),
                'mail_sent' => false,
            ]);
            foreach ($items as $item) {
                purchase_items::create([
                    "purchase_id" => $purchase->id,
                    "image_id"    => $item->image_id,
                    "price"       => $item->price,
                ]);
            }
            cartItems::where('cart_id', $cart->id)->delete();
            $this->clearCartCache($user->id);
                Mail::to($purchase->user->email)->queue(
                    new PaymentSuccessMail($purchase)
                    );
            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'paid_amount' => $total,
                'balance' => $wallet->amount
            ], 200);

        });
    }
}
