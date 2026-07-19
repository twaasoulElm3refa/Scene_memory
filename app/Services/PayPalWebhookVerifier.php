<?php

namespace App\Services;

use Illuminate\Http\Request;
use JsonException;

class PayPalWebhookVerifier
{
    private const HEADERS = [
        'PAYPAL-TRANSMISSION-ID',
        'PAYPAL-TRANSMISSION-TIME',
        'PAYPAL-CERT-URL',
        'PAYPAL-AUTH-ALGO',
        'PAYPAL-TRANSMISSION-SIG',
    ];

    public function __construct(private readonly PayPalGateway $gateway) {}

    /** @throws JsonException */
    public function verify(Request $request, string $rawBody, string $webhookId): bool
    {
        if ($webhookId === '') {
            return false;
        }
        foreach (self::HEADERS as $header) {
            if (! is_string($request->header($header)) || trim((string) $request->header($header)) === '') {
                return false;
            }
        }

        $certUrl = (string) $request->header('PAYPAL-CERT-URL');
        $expectedHost = config('paypal.mode') === 'live' ? 'api-m.paypal.com' : 'api-m.sandbox.paypal.com';
        if (parse_url($certUrl, PHP_URL_SCHEME) !== 'https' || parse_url($certUrl, PHP_URL_HOST) !== $expectedHost) {
            return false;
        }

        $webhookEvent = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        $result = $this->gateway->verifyWebhookSignature([
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'cert_url' => $certUrl,
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'webhook_id' => $webhookId,
            'webhook_event' => $webhookEvent,
        ]);

        return ($result['verification_status'] ?? null) === 'SUCCESS';
    }
}
