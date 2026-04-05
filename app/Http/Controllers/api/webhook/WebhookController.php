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
        Log::info('PayPal Webhook: Request received', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'content_type' => $request->header('Content-Type'),
        ]);

        // 🧠 جلب Raw Body
        $body = $request->getContent();

        Log::info('PayPal Webhook: Raw body received', [
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 500) . (strlen($body) > 500 ? '...' : '')
        ]);

        // 🧠 جلب الـ Headers المهمة
        $headers = [
            'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'cert_url'          => $request->header('PAYPAL-CERT-URL'),
            'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
            'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
        ];

        Log::info('PayPal Webhook: Headers extracted', [
            'transmission_id'   => $headers['transmission_id'],
            'transmission_time' => $headers['transmission_time'],
            'auth_algo'         => $headers['auth_algo'],
            'cert_url_present'  => !empty($headers['cert_url']),
            'signature_present' => !empty($headers['transmission_sig']),
        ]);

        $webhookId = config('paypal.webhook_id');

        Log::info('PayPal Webhook: Webhook ID from config', [
            'webhook_id' => $webhookId,
            'config_exists' => config('paypal.webhook_id') !== null
        ]);

        if (empty($webhookId)) {
            Log::error('PayPal Webhook: webhook_id is not configured in config/paypal.php');
            return response()->json(['error' => 'webhook_id not configured'], 500);
        }

        try {
            Log::info('PayPal Webhook: Initializing PayPal provider...');

            $provider = new \Srmklive\PayPal\Services\PayPal;
            $provider->setApiCredentials(config('paypal'));

            Log::info('PayPal Webhook: Getting access token...');
            $provider->getAccessToken();

            Log::info('PayPal Webhook: Preparing webhook verification data...');

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

            Log::info('PayPal Webhook: Calling verifyWebHook...');

            $verify = $provider->verifyWebHook($verifyData);

            Log::info('PayPal Webhook: Verification response received', [
                'verification_status' => $verify['verification_status'] ?? 'UNKNOWN',
                'full_response' => $verify
            ]);

            if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
                Log::warning('PayPal Webhook: Signature verification FAILED', [
                    'ip' => $request->ip(),
                    'transmission_id' => $headers['transmission_id'],
                    'verification_status' => $verify['verification_status'] ?? 'UNKNOWN'
                ]);
                return response()->json(['status' => 'invalid'], 400);
            }

            Log::info('PayPal Webhook: Signature verification SUCCESS');

            // في WebhookController مؤقتاً للـ sandbox فقط
            if (config('paypal.mode') === 'sandbox') {
                Log::warning('PayPal Webhook: Skipping verification in sandbox mode');
                $eventData = json_decode($body, true);
                $this->paypal->handleWebhook($eventData);
                return response()->json(['status' => 'ok']);
            }
            // ✅ التحقق نجح → معالجة الـ Event
            Log::info('PayPal Webhook: Starting event processing via PayPalServices...');

            $eventData = json_decode($body, true);
            $this->paypal->handleWebhook($eventData);

            Log::info('PayPal Webhook: Event processed successfully', [
                'event_type' => $eventData['event_type'] ?? 'unknown',
                'event_id'   => $eventData['id'] ?? 'unknown'
            ]);

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('PayPal Webhook: Exception occurred', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'ip'      => $request->ip(),
                'transmission_id' => $headers['transmission_id'] ?? null
            ]);

            return response()->json(['error' => 'webhook failed'], 500);
        }
    }
}
