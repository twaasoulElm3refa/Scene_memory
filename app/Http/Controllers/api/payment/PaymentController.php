<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class PaymentController extends Controller
{
    public function __construct(protected PayPalService $paypal) {}

    /**
     * POST /api/pay
     * Body: { "amount": 49.99, "description": "Pro Plan" }
     */
    public function pay(Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $approvalUrl = $this->paypal->pay($request->only('amount', 'description'));

            return response()->json([
                'success'      => true,
                'approval_url' => $approvalUrl,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/paypal/success?token=xxx
     * PayPal بيبعت المستخدم هنا بعد الموافقة
     */
    public function success(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Token missing.'], 400);
        }

        try {
            $result = $this->paypal->success($token);

            // ✅ هنا تحفظ في الـ DB أو تعمل أي logic تاني
            // Payment::create([...]);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/paypal/cancel
     */
    public function cancel(): JsonResponse
    {
        return response()->json($this->paypal->cancel());
    }
}
