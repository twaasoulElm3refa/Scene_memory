<?php

namespace App\Services;

use Illuminate\Http\Request;
use JsonException;

class PayPalWebhookVerifier
{
    public function __construct(private readonly PayPalGateway $gateway) {}

    /**
     * @throws JsonException
     */
    public function verify(Request $request, string $rawBody, string $webhookId): bool
    {
        $webhookEvent = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);

        $result = $this->gateway->verifyWebhookSignature([
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'webhook_id' => $webhookId,
            'webhook_event' => $webhookEvent,
        ]);

        return ($result['verification_status'] ?? null) === 'SUCCESS';
    }
}
