<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Services\PayPalServices;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

// ══════════════════════════════════════════════════════════════════════════════
// PaymentController  —  User-facing endpoints
// ══════════════════════════════════════════════════════════════════════════════

class PaymentController extends Controller
{
    public function __construct(protected PayPalServices $paypal) {}

    public function pay(Request $request): JsonResponse
    {
        Log::info('PayPal Payment: pay() endpoint called', [
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->header('User-Agent'),
        ]);

        // Validation
        $validated = $request->validate([
            'amount'          => 'required|numeric|min:0.01',
            'description'     => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        Log::info('PayPal Payment: Input validated successfully', [
            'amount'          => $validated['amount'],
            'description'     => $validated['description'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
            'user_id'         => $request->user()?->id,
        ]);

        $validated['user_id'] = $request->user()?->id;

        try {
            Log::info('PayPal Payment: Calling PayPalServices->pay()...', [
                'amount' => $validated['amount'],
                'user_id' => $validated['user_id']
            ]);

            ['order' => $order, 'approval_url' => $url] = $this->paypal->pay($validated);

            Log::info('PayPal Payment: Order created successfully', [
                'order_id'     => $order->id,
                'amount'       => $validated['amount'],
                'user_id'      => $validated['user_id'],
                'approval_url' => $url,
            ]);

            return response()->json([
                'success'      => true,
                'order_id'     => $order->id,
                'approval_url' => $url,
            ]);

        } catch (Exception $e) {
            Log::error('PayPal Payment: Exception in pay() method', [
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'user_id'     => $request->user()?->id,
                'amount'      => $validated['amount'] ?? null,
                'trace'       => $e->getTraceAsString(),
            ]);

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
    public function success(Request $request): JsonResponse
    {
        $token = $request->query('token');

        Log::info('PayPal Payment: success() endpoint called', [
            'ip'    => $request->ip(),
            'token' => $token ? substr($token, 0, 10) . '...' : null,
            'user_id' => $request->user()?->id,
        ]);

        if (!$token) {
            Log::warning('PayPal Payment: Token missing in success callback', [
                'ip' => $request->ip()
            ]);
            return response()->json(['success' => false, 'message' => 'Token missing.'], 400);
        }

        try {
            Log::info('PayPal Payment: Calling PayPalServices->success()...', ['token' => substr($token, 0, 15) . '...']);

            $result = $this->paypal->success($token);

            Log::info('PayPal Payment: success() processed successfully', [
                'result' => $result,
                'token'  => substr($token, 0, 15) . '...'
            ]);

            return response()->json($result);

        } catch (Exception $e) {
            Log::error('PayPal Payment: Exception in success() method', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'token'   => substr($token, 0, 15) . '...',
                'user_id' => $request->user()?->id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── GET /api/paypal/cancel ────────────────────────────────────────────────
    public function cancel(): JsonResponse
    {
        Log::info('PayPal Payment: cancel() endpoint called', [
            'ip' => request()->ip(),
            'user_id' => request()->user()?->id,
        ]);

        try {
            $result = $this->paypal->cancel();

            Log::info('PayPal Payment: cancel() processed successfully', [
                'result' => $result
            ]);

            return response()->json($result);

        } catch (Exception $e) {
            Log::error('PayPal Payment: Exception in cancel() method', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => request()->user()?->id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
