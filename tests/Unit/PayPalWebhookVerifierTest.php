<?php

namespace Tests\Unit;

use App\Services\PayPalGateway;
use App\Services\PayPalWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PayPalWebhookVerifierTest extends TestCase
{
    private string $certificatePem;

    private mixed $privateKey;

    private string $certUrl = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/unit-test.pem';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new([
            'commonName' => 'api-m.sandbox.paypal.com',
            'organizationName' => 'Unit Test PayPal',
        ], $this->privateKey, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $this->privateKey, 1, ['digest_alg' => 'sha256']);
        openssl_x509_export($certificate, $certificatePem);
        $this->certificatePem = $certificatePem;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_success_uses_original_raw_body_for_crc32(): void
    {
        $rawBody = '{ "event_type":"PAYMENT.CAPTURE.COMPLETED","id":"WH-UNIT","resource":{"id":"CAPTURE-1"} }';
        $request = $this->signedRequest($rawBody, 'configured-webhook-id');

        $this->assertTrue($this->verifier()->verify($request, $rawBody, 'configured-webhook-id', 'checkout'));
    }

    public function test_changing_one_character_in_raw_body_fails_verification(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-1"}}';
        $changedRawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-2"}}';
        $request = $this->signedRequest($rawBody, 'configured-webhook-id');

        $this->assertFalse($this->verifier()->verify($request, $changedRawBody, 'configured-webhook-id', 'checkout'));
    }

    public function test_different_webhook_id_fails_verification(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $request = $this->signedRequest($rawBody, 'configured-webhook-id');

        $this->assertFalse($this->verifier()->verify($request, $rawBody, 'other-webhook-id', 'checkout'));
    }

    public function test_different_transmission_time_fails_verification(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $request = $this->signedRequest($rawBody, 'configured-webhook-id', '2026-07-18T10:00:00Z');
        $request->headers->set('PAYPAL-TRANSMISSION-TIME', '2026-07-18T10:00:01Z');

        $this->assertFalse($this->verifier()->verify($request, $rawBody, 'configured-webhook-id', 'checkout'));
    }

    public function test_certificate_url_outside_allowlist_is_rejected(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $request = $this->signedRequest(
            $rawBody,
            'configured-webhook-id',
            '2026-07-18T10:00:00Z',
            'https://attacker.example/v1/notifications/certs/unit-test.pem'
        );

        $this->assertFalse($this->verifier()->verify($request, $rawBody, 'configured-webhook-id', 'checkout'));
    }

    public function test_http_certificate_url_is_rejected(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $request = $this->signedRequest(
            $rawBody,
            'configured-webhook-id',
            '2026-07-18T10:00:00Z',
            'http://api-m.sandbox.paypal.com/v1/notifications/certs/unit-test.pem'
        );

        $this->assertFalse($this->verifier()->verify($request, $rawBody, 'configured-webhook-id', 'checkout'));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $rawBody = '{"id":"WH-UNIT","event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $request = $this->signedRequest($rawBody, 'configured-webhook-id');
        $request->headers->set('PAYPAL-TRANSMISSION-SIG', base64_encode('invalid-signature'));

        $this->assertFalse($this->verifier()->verify($request, $rawBody, 'configured-webhook-id', 'checkout'));
    }

    public function test_missing_signature_headers_fail_closed(): void
    {
        $request = Request::create('/api/v1/paypal/webhook', 'POST', [], [], [], [], '{"id":"WH"}');

        $this->assertFalse($this->verifier()->verify($request, $request->getContent(), 'configured-webhook-id'));
    }

    private function signedRequest(
        string $rawBody,
        string $webhookId,
        string $transmissionTime = '2026-07-18T10:00:00Z',
        ?string $certUrl = null,
    ): Request {
        $certUrl ??= $this->certUrl;
        Http::fake([
            $certUrl => Http::response($this->certificatePem, 200),
        ]);

        $transmissionId = 'transmission-id';
        $crc32 = sprintf('%u', crc32($rawBody));
        $signedMessage = implode('|', [
            $transmissionId,
            $transmissionTime,
            $webhookId,
            $crc32,
        ]);

        openssl_sign($signedMessage, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return Request::create('/api/v1/paypal/webhook', 'POST', [], [], [], [
            'HTTP_PAYPAL_TRANSMISSION_ID' => $transmissionId,
            'HTTP_PAYPAL_TRANSMISSION_TIME' => $transmissionTime,
            'HTTP_PAYPAL_CERT_URL' => $certUrl,
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => base64_encode($signature),
        ], $rawBody);
    }

    private function verifier(): PayPalWebhookVerifier
    {
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldNotReceive('verifyWebhookSignature');

        return new PayPalWebhookVerifier($gateway);
    }
}
