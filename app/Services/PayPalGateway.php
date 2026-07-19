<?php

namespace App\Services;

use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalGateway
{
    public function createOrder(array $data, string $requestId): array
    {
        $client = $this->client();
        $client->setRequestHeader('PayPal-Request-Id', $requestId);

        return $client->createOrder($data);
    }

    public function showOrderDetails(string $orderId): array
    {
        return $this->client()->showOrderDetails($orderId);
    }

    public function capturePaymentOrder(string $orderId, string $requestId): array
    {
        $client = $this->client();
        $client->setRequestHeader('PayPal-Request-Id', $requestId);

        return $client->capturePaymentOrder($orderId);
    }

    public function verifyWebhookSignature(array $data): array
    {
        return $this->client()->verifyWebHook($data);
    }

    private function client(): PayPalClient
    {
        $client = new PayPalClient;
        $client->setApiCredentials(config('paypal'));
        $client->getAccessToken();

        return $client;
    }
}
