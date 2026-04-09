<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Services\PayPalServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected PayPalServices $paypal) {}

    public function handle(Request $request)
    {

        // 🧠 جلب Raw Body
        $body = $request->getContent();

        // 🧠 جلب الـ Headers المهمة
        $headers = [
            'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'cert_url'          => $request->header('PAYPAL-CERT-URL'),
            'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
            'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
        ];

        $webhookId = config('paypal.webhook_id');


        if (empty($webhookId)) {
            return response()->json(['error' => 'webhook_id not configured'], 500);
        }

        try {
            $provider = new \Srmklive\PayPal\Services\PayPal;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

           $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON body', [
                    'error' => json_last_error_msg()
                ]);
            }

            $verifyData = [
            'transmission_id'   => $headers['transmission_id'],
            'transmission_time' => $headers['transmission_time'],
            'cert_url'          => $headers['cert_url'],
            'auth_algo'         => $headers['auth_algo'],
            'transmission_sig'  => $headers['transmission_sig'],
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($body), // object مش array
            ];


            $verify = $provider->verifyWebHook($verifyData);



            if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
                Log::warning('PayPal Webhook: Signature verification FAILED', [
                    'ip' => $request->ip(),
                    'transmission_id' => $headers['transmission_id'],
                    'verification_status' => $verify['verification_status'] ?? 'UNKNOWN'
                ]);
                return response()->json(['status' => 'invalid'], 400);
            }

            // في WebhookController مؤقتاً للـ sandbox فقط
            if (config('paypal.mode') === 'sandbox') {
                Log::warning('PayPal Webhook: Skipping verification in sandbox mode');
                $eventData = json_decode($body, true);
                $this->paypal->handleWebhook($eventData);
                return response()->json(['status' => 'ok']);
            }

            $eventData = json_decode($body, true);
            $this->paypal->handleWebhook($eventData);

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'webhook failed'], 500);
        }
    }
}
