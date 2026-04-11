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

        if (json_decode($body, true) === null) {
            Log::error('WalletWebhook: Invalid JSON body');
            return response()->json(['error' => 'invalid json'], 400);
        }

        // استخدم الـ wallet_webhook_id مش الـ webhook_id العادي
        $webhookId = config('paypal.wallet_webhook_id');

        if (empty($webhookId)) {
            Log::error('WalletWebhook: wallet_webhook_id not configured');
            return response()->json(['error' => 'wallet_webhook_id not configured'], 500);
        }

        // Sandbox: تخطي الـ verification
        if (config('paypal.mode') === 'sandbox') {
            Log::warning('WalletWebhook: Skipping verification in sandbox mode');
            $eventData = json_decode($body, true);
            $this->paypal->handleWebhook($eventData);
            return response()->json(['status' => 'ok']);
        }

        // Production: verify الـ signature
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
                'webhook_id'        => $webhookId, // ← الـ wallet webhook_id
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

            $eventData = json_decode($body, true);
            $this->paypal->handleWebhook($eventData);
            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('WalletWebhook: Exception', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'webhook failed'], 500);
        }
    }
}
