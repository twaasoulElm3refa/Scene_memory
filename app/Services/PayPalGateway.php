<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use JsonException;
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
        $tokenResult = $this->accessToken();
        if (! is_string($tokenResult['access_token'] ?? null) || $tokenResult['access_token'] === '') {
            return [
                'verification_status' => null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'verify_http_status' => null,
                'name' => $tokenResult['name'] ?? 'PAYPAL_ACCESS_TOKEN_FAILED',
                'message' => $tokenResult['message'] ?? 'PayPal access token request failed.',
                'debug_id' => $tokenResult['debug_id'] ?? null,
                'response_body' => $this->safeBody($tokenResult['body'] ?? null),
            ];
        }

        try {
            $rawEvent = $data['webhook_event_raw'] ?? '';

            if (! is_string($rawEvent) || trim($rawEvent) === '') {
                return [
                    'verification_status' => null,
                    'access_token_http_status' => $tokenResult['http_status'] ?? null,
                    'verify_http_status' => null,
                    'name' => 'PAYPAL_INVALID_WEBHOOK_EVENT',
                    'message' => 'Original PayPal webhook body is missing.',
                    'debug_id' => null,
                    'response_body' => null,
                ];
            }

            $event = json_decode(
                $rawEvent,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (! is_array($event)) {
                return [
                    'verification_status' => null,
                    'access_token_http_status' => $tokenResult['http_status'] ?? null,
                    'verify_http_status' => null,
                    'name' => 'PAYPAL_INVALID_WEBHOOK_EVENT',
                    'message' => 'PayPal webhook event JSON must decode to an object or array.',
                    'debug_id' => null,
                    'response_body' => null,
                ];
            }

            $response = Http::acceptJson()
                ->asJson()
                ->withToken($tokenResult['access_token'])
                ->timeout($this->timeout())
                ->retry(
                    $this->retryTimes(),
                    $this->retrySleep(),
                    throw: false
                )
                ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $data['auth_algo'] ?? null,
                    'cert_url' => $data['cert_url'] ?? null,
                    'transmission_id' => $data['transmission_id'] ?? null,
                    'transmission_sig' => $data['transmission_sig'] ?? null,
                    'transmission_time' => $data['transmission_time'] ?? null,
                    'webhook_id' => $data['webhook_id'] ?? null,
                    'webhook_event' => $event,
                ]);
        } catch (JsonException $exception) {
            return [
                'verification_status' => null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'verify_http_status' => null,
                'name' => 'PAYPAL_INVALID_WEBHOOK_EVENT',
                'message' => $exception->getMessage(),
                'debug_id' => null,
                'response_body' => null,
            ];
        } catch (RequestException $exception) {
            $response = $exception->response;

            if ($response === null) {
                return [
                    'verification_status' => null,
                    'access_token_http_status' => $tokenResult['http_status'] ?? null,
                    'verify_http_status' => null,
                    'name' => 'PAYPAL_VERIFY_REQUEST_EXCEPTION',
                    'message' => $exception->getMessage(),
                    'debug_id' => null,
                    'response_body' => null,
                ];
            }

            $body = $response->json();

            if (! is_array($body)) {
                $body = [
                    'raw' => $response->body(),
                ];
            }

            return [
                'verification_status' => $body['verification_status'] ?? null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'verify_http_status' => $response->status(),
                'name' => $body['name'] ?? 'PAYPAL_VERIFY_REQUEST_EXCEPTION',
                'message' => $body['message'] ?? $exception->getMessage(),
                'debug_id' => $body['debug_id'] ?? null,
                'response_body' => $this->safeBody($body),
            ];
        } catch (ConnectionException $exception) {
            return [
                'verification_status' => null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'verify_http_status' => null,
                'name' => 'PAYPAL_VERIFY_CONNECTION_EXCEPTION',
                'message' => $exception->getMessage(),
                'debug_id' => null,
                'response_body' => null,
            ];
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = ['raw' => $response->body()];
        }

        return [
            'verification_status' => $body['verification_status'] ?? null,
            'access_token_http_status' => $tokenResult['http_status'] ?? null,
            'verify_http_status' => $response->status(),
            'name' => $body['name'] ?? null,
            'message' => $body['message'] ?? null,
            'debug_id' => $body['debug_id'] ?? null,
            'response_body' => $this->safeBody($body),
        ];
    }

    public function accessTokenProbe(): array
    {
        $result = $this->accessToken();

        return Arr::except($result, ['access_token']);
    }

    public function showWebhook(string $webhookId): array
    {
        $tokenResult = $this->accessToken();
        if (! is_string($tokenResult['access_token'] ?? null) || $tokenResult['access_token'] === '') {
            return [
                'http_status' => null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'name' => $tokenResult['name'] ?? 'PAYPAL_ACCESS_TOKEN_FAILED',
                'message' => $tokenResult['message'] ?? 'PayPal access token request failed.',
                'debug_id' => $tokenResult['debug_id'] ?? null,
                'body' => $this->safeBody($tokenResult['body'] ?? null),
            ];
        }

       try {
            $rawEvent = $data['webhook_event_raw'] ?? '';

            if (! is_string($rawEvent) || trim($rawEvent) === '') {
                return [
                    'verification_status' => null,
                    'access_token_http_status' => $tokenResult['http_status'] ?? null,
                    'verify_http_status' => null,
                    'name' => 'PAYPAL_RAW_WEBHOOK_MISSING',
                    'message' => 'Original PayPal webhook body is missing.',
                    'debug_id' => null,
                    'response_body' => null,
                ];
            }

            // نتأكد فقط أن الـ raw body عبارة عن JSON صالح.
            json_decode($rawEvent, true, 512, JSON_THROW_ON_ERROR);

            $verificationHeaders = [
                'auth_algo' => $data['auth_algo'] ?? null,
                'cert_url' => $data['cert_url'] ?? null,
                'transmission_id' => $data['transmission_id'] ?? null,
                'transmission_sig' => $data['transmission_sig'] ?? null,
                'transmission_time' => $data['transmission_time'] ?? null,
                'webhook_id' => $data['webhook_id'] ?? null,
            ];

            $encodedHeaders = json_encode(
                $verificationHeaders,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            // إدخال webhook_event بالجسم الأصلي كما وصل من PayPal.
            $verificationBody = substr($encodedHeaders, 0, -1)
                . ',"webhook_event":'
                . $rawEvent
                . '}';

            $response = Http::acceptJson()
                ->withToken($tokenResult['access_token'])
                ->withBody($verificationBody, 'application/json')
                ->timeout($this->timeout())
                ->retry(
                    $this->retryTimes(),
                    $this->retrySleep(),
                    throw: false
                )
                ->post(
                    $this->baseUrl().'/v1/notifications/verify-webhook-signature'
                );
        } catch (JsonException $exception) {
            return [
                'verification_status' => null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'verify_http_status' => null,
                'name' => 'PAYPAL_INVALID_RAW_WEBHOOK',
                'message' => $exception->getMessage(),
                'debug_id' => null,
                'response_body' => null,
            ];
        } catch (ConnectionException $exception) {
            return [
                'verification_status' => null,
                'access_token_http_status' => $tokenResult['http_status'] ?? null,
                'verify_http_status' => null,
                'name' => 'PAYPAL_VERIFY_CONNECTION_EXCEPTION',
                'message' => $exception->getMessage(),
                'debug_id' => null,
                'response_body' => null,
            ];
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = ['raw' => $response->body()];
        }

        return [
            'http_status' => $response->status(),
            'access_token_http_status' => $tokenResult['http_status'] ?? null,
            'name' => $body['name'] ?? null,
            'message' => $body['message'] ?? null,
            'debug_id' => $body['debug_id'] ?? null,
            'body' => $this->safeBody($body),
        ];
    }

    private function client(): PayPalClient
    {
        $client = new PayPalClient;
        $client->setApiCredentials(config('paypal'));
        $client->getAccessToken();

        return $client;
    }

    private function accessToken(): array
    {
        $mode = $this->mode();
        $clientId = (string) config("paypal.{$mode}.client_id");
        $clientSecret = (string) config("paypal.{$mode}.client_secret");

        if ($clientId === '' || $clientSecret === '') {
            return [
                'access_token' => null,
                'http_status' => null,
                'name' => 'PAYPAL_CREDENTIALS_MISSING',
                'message' => 'PayPal client ID or client secret is not configured for the active mode.',
                'debug_id' => null,
                'body' => null,
            ];
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout($this->timeout())
                ->retry($this->retryTimes(), $this->retrySleep())
                ->post($this->baseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException $exception) {
            return [
                'access_token' => null,
                'http_status' => null,
                'name' => 'PAYPAL_ACCESS_TOKEN_CONNECTION_EXCEPTION',
                'message' => $exception->getMessage(),
                'debug_id' => null,
                'body' => null,
            ];
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = ['raw' => $response->body()];
        }

        return [
            'access_token' => $response->successful() ? ($body['access_token'] ?? null) : null,
            'http_status' => $response->status(),
            'name' => $body['name'] ?? $body['error'] ?? null,
            'message' => $body['message'] ?? $body['error_description'] ?? null,
            'debug_id' => $body['debug_id'] ?? null,
            'body' => $this->safeBody($body),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('paypal.api.'.$this->mode().'_url'), '/');
    }

    private function mode(): string
    {
        return config('paypal.mode') === 'live' ? 'live' : 'sandbox';
    }

    private function timeout(): int
    {
        return max(1, (int) config('paypal.api.timeout', 10));
    }

    private function retryTimes(): int
    {
        return max(0, (int) config('paypal.api.retry_times', 2));
    }

    private function retrySleep(): int
    {
        return max(0, (int) config('paypal.api.retry_sleep_ms', 200));
    }

    private function safeBody(mixed $body): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

        return collect($body)
            ->mapWithKeys(function (mixed $value, string|int $key): array {
                $normalized = strtolower((string) $key);
                if (in_array($normalized, ['access_token', 'refresh_token', 'client_secret', 'transmission_sig'], true)) {
                    return [$key => '[redacted]'];
                }

                return [$key => is_array($value) ? $this->safeBody($value) : $value];
            })
            ->all();
    }
}
