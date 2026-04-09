<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Mail\PaymentFailMail;
use App\Models\cart;
use App\Models\cartItems;
use App\Services\PayPalServices;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use App\Http\Controllers\concerns\ApiResponse;
use App\Mail\PaymentSuccessMail;
use App\Models\purchase_items;
use Illuminate\Support\Facades\Cache;
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
        if($order->status == "completed"){
            Mail::to($order->user->email)->send(
                    new PaymentSuccessMail(
                        $order->amount,
                        $order->user->name
                    )
                );
            return response()->json(['status' => $order->status]);
        }
        else{
            Mail::to($order->user->email)->send(
                    new PaymentFailMail(
                        $order->amount,
                        $order->user->name
                    )
                );
            return response()->json(['status' => $order->status]);
        }
    }
}
