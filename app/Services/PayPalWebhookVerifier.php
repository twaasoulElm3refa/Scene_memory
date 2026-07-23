<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

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
    public function verify(Request $request, string $rawBody, string $webhookId, string $webhookType = 'unknown'): bool
    {
        $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $context = $this->context($request, $webhookId, $webhookType, is_array($event) ? $event : []);
        $transmissionId = trim((string) $request->header('PAYPAL-TRANSMISSION-ID'));
        $transmissionTime = trim((string) $request->header('PAYPAL-TRANSMISSION-TIME'));
        $certUrl = trim((string) $request->header('PAYPAL-CERT-URL'));
        $authAlgo = trim((string) $request->header('PAYPAL-AUTH-ALGO'));
        $transmissionSig = trim((string) $request->header('PAYPAL-TRANSMISSION-SIG'));
        $certHost = strtolower((string) parse_url($certUrl, PHP_URL_HOST));
        $crc32 = $rawBody === '' ? null : sprintf('%u', crc32($rawBody));

        if ($webhookId === '' || $rawBody === '') {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'WEBHOOK_ID_OR_BODY_MISSING');
            return false;
        }

        if ($transmissionId === '' || $transmissionTime === '' || $certUrl === ''
            || $authAlgo === '' || $transmissionSig === '') {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'HEADER_MISSING');
            return false;
        }

        if (! $this->validCertificateUrl($certUrl)) {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'INVALID_CERT_URL');
            return false;
        }

        if (strcasecmp($authAlgo, 'SHA256withRSA') !== 0) {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'UNSUPPORTED_AUTH_ALGO');
            return false;
        }

        $signedMessage = implode('|', [
            $transmissionId,
            $transmissionTime,
            $webhookId,
            $crc32,
        ]);

        $certificatePem = $this->certificate($certUrl);
        if ($certificatePem === null) {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'CERTIFICATE_DOWNLOAD_FAILED');
            return false;
        }

        $publicKey = openssl_pkey_get_public($certificatePem);
        if ($publicKey === false) {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'CERTIFICATE_PUBLIC_KEY_INVALID');
            return false;
        }

        $signature = base64_decode($transmissionSig, true);
        if ($signature === false) {
            $this->logLocalResult($context, $certHost, $authAlgo, $crc32, 'FAILURE', null, 'SIGNATURE_BASE64_INVALID');
            return false;
        }

        $verificationResult = openssl_verify(
            $signedMessage,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        $success = $verificationResult === 1;
        $this->logLocalResult(
            $context,
            $certHost,
            $authAlgo,
            $crc32,
            $success ? 'SUCCESS' : 'FAILURE',
            $verificationResult,
            $success ? null : ($verificationResult === 0 ? 'SIGNATURE_INVALID' : 'OPENSSL_VERIFY_ERROR')
        );

        if ($success) {
            Log::info('webhook_signature_verified', [
                'event_id' => $context['event_id'],
                'event_type' => $context['event_type'],
                'webhook_type' => $webhookType,
            ]);
        }

        return $success;
    }

    private function context(Request $request, string $webhookId, string $webhookType, array $event): array
    {
        return [
            'mode' => config('paypal.mode') === 'live' ? 'live' : 'sandbox',
            'webhook_type' => $webhookType,
            'webhook_id' => $webhookId,
            'event_id' => is_string($event['id'] ?? null) ? $event['id'] : null,
            'event_type' => is_string($event['event_type'] ?? null) ? $event['event_type'] : null,
            'headers_present' => collect(self::HEADERS)
                ->mapWithKeys(fn (string $header): array => [$header => filled($request->header($header))])
                ->all(),
        ];
    }

    private function validCertificateUrl(string $certUrl): bool
    {
        if (filter_var($certUrl, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($certUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedHosts = [
            'api.paypal.com',
            'api.sandbox.paypal.com',
            'api-m.paypal.com',
            'api-m.sandbox.paypal.com',
        ];

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && (! isset($parts['port']) || (int) $parts['port'] === 443)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && in_array($host, $allowedHosts, true)
            && str_starts_with((string) ($parts['path'] ?? ''), '/v1/notifications/certs/');
    }

    private function certificate(string $certUrl): ?string
    {
        try {
            return Cache::remember(
                'paypal:certificate:'.hash('sha256', $certUrl),
                now()->addHours(12),
                function () use ($certUrl): string {
                    $response = Http::timeout(10)
                        ->retry(2, 200, throw: false)
                        ->get($certUrl);

                    if (! $response->successful() || trim($response->body()) === '') {
                        throw new RuntimeException('PayPal certificate download failed.');
                    }

                    return $response->body();
                }
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function logLocalResult(
        array $context,
        string $certHost,
        string $authAlgo,
        ?string $crc32,
        string $verificationStatus,
        int|false|null $opensslResult,
        ?string $errorName,
    ): void {
        $payload = [
            'mode' => $context['mode'],
            'webhook_type' => $context['webhook_type'],
            'webhook_id' => $context['webhook_id'],
            'event_id' => $context['event_id'],
            'event_type' => $context['event_type'],
            'cert_host' => $certHost,
            'auth_algo' => $authAlgo,
            'crc32' => $crc32,
            'verification_status' => $verificationStatus,
            'openssl_result' => $opensslResult,
            'error_name' => $errorName,
        ];

        if ($verificationStatus === 'SUCCESS') {
            Log::info('paypal_webhook_local_verification_result', $payload);
            return;
        }

        Log::warning('paypal_webhook_local_verification_result', $payload);
    }
}
