<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Services\PayPalWalletServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletWebhookController extends Controller
{
    public function __construct(protected PayPalWalletServices $paypal) {}

    public function handle(Request $request)
    {
        $body = $request->getContent();
        $eventData = json_decode($body, true);

        if (!is_array($eventData)) {
            Log::error('WalletWebhook: Invalid JSON body');
            return response()->json(['error' => 'invalid json'], 400);
        }

        $webhookId = config('paypal.wallet_webhook_id', config('paypal.webhook_id'));

        if (empty($webhookId)) {
            Log::error('WalletWebhook: webhook_id not configured');
            return response()->json(['error' => 'webhook_id not configured'], 500);
        }

        // Production verification
        if (config('paypal.mode') !== 'sandbox') {
            try {
                $provider = new \Srmklive\PayPal\Services\PayPal;
                $provider->setApiCredentials(config('paypal'));
                $provider->getAccessToken();

                $verifyData = [
                    'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'cert_url'          => $request->header('PAYPAL-CERT-URL'),
                    'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
                    'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'webhook_id'        => $webhookId,
                    'webhook_event'     => json_decode($body),
                ];

                $verify = $provider->verifyWebHook($verifyData);

                if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
                    Log::warning('WalletWebhook: Signature verification FAILED', [
                        'ip'                  => $request->ip(),
                        'transmission_id'     => $request->header('PAYPAL-TRANSMISSION-ID'),
                        'verification_status' => $verify['verification_status'] ?? 'UNKNOWN',
                    ]);
                    return response()->json(['status' => 'invalid'], 400);
                }
            } catch (\Exception $e) {
                Log::error('WalletWebhook: Verification exception', [
                    'message' => $e->getMessage(),
                ]);
                return response()->json(['error' => 'webhook verification failed'], 500);
            }
        } else {
            Log::warning('WalletWebhook: Sandbox mode - signature verification skipped');
        }

        $paypalOrderId = $this->extractPaypalOrderId($eventData);

        if (!$paypalOrderId) {
            Log::warning('WalletWebhook: Could not resolve paypal_order_id', [
                'event_type' => $eventData['event_type'] ?? null,
            ]);
            return response()->json(['status' => 'ignored']);
        }

        $order = \App\Models\purchases::where('paypal_order_id', $paypalOrderId)->first();

        if (!$order) {
            Log::warning('WalletWebhook: Order not found for webhook', [
                'paypal_order_id' => $paypalOrderId,
            ]);
            return response()->json(['status' => 'ignored']);
        }

        if ($order->type !== 'wallet_deposit') {
            Log::warning('WalletWebhook: Ignoring non-wallet event', [
                'order_id'        => $order->id,
                'type'            => $order->type,
                'paypal_order_id' => $paypalOrderId,
            ]);
            return response()->json(['status' => 'ignored']);
        }

        Log::info('Wallet webhook received', [
            'event_type'      => $eventData['event_type'] ?? null,
            'paypal_order_id' => $paypalOrderId,
            'order_id'        => $order->id,
        ]);

        $this->paypal->handleWebhook($eventData);

        return response()->json(['status' => 'ok']);
    }

    private function extractPaypalOrderId(array $eventData): ?string
    {
        $eventType = $eventData['event_type'] ?? null;
        $resource  = $eventData['resource'] ?? [];

        return match ($eventType) {
            'CHECKOUT.ORDER.APPROVED'   => $resource['id'] ?? null,
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DECLINED'  => $resource['supplementary_data']['related_ids']['order_id'] ?? null,
            default                     => $resource['id'] ?? null,
        };
    }
}
