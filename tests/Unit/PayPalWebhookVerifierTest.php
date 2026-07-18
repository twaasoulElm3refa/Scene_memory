<?php

namespace Tests\Unit;

use App\Services\PayPalGateway;
use App\Services\PayPalWebhookVerifier;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

class PayPalWebhookVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_true_only_for_paypal_success_response(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-1"}}';
        $request = Request::create('/api/v1/paypal/webhook', 'POST', [], [], [], [
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-id',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-07-18T10:00:00Z',
            'HTTP_PAYPAL_CERT_URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
        ], $rawBody);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('verifyWebhookSignature')
            ->once()
            ->withArgs(function (array $data) {
                return $data['transmission_id'] === 'transmission-id'
                    && $data['webhook_id'] === 'configured-webhook-id'
                    && $data['webhook_event']->id === 'WH-UNIT';
            })
            ->andReturn(['verification_status' => 'SUCCESS']);

        $verifier = new PayPalWebhookVerifier($gateway);

        $this->assertTrue($verifier->verify($request, $rawBody, 'configured-webhook-id'));
    }
}
