<?php

namespace App\Services;

use App\Models\PaypalWebhookEvent;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class PayPalWebhookProcessor
{
    private const SUPPORTED_EVENTS = [
        'CHECKOUT.ORDER.APPROVED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.PENDING',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.REFUNDED',
        'PAYMENT.CAPTURE.REVERSED',
    ];

    public function __construct(
        private readonly PayPalWebhookVerifier $verifier,
        private readonly PayPalOrderResolver $resolver,
        private readonly PayPalCaptureData $captureData,
    ) {}

    public function process(Request $request, ?string $webhookId, string $webhookType, Closure $handler): JsonResponse
    {
        $rawBody = $request->getContent();
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['status' => 'invalid'], 400);
        }
        if (! is_array($payload) || ! is_string($payload['id'] ?? null) || trim($payload['id']) === ''
            || ! is_string($payload['event_type'] ?? null) || trim($payload['event_type']) === '') {
            return response()->json(['status' => 'invalid'], 400);
        }

        $eventId = $payload['id'];
        $eventType = $payload['event_type'];
        Log::info('webhook_received', ['event_id' => $eventId, 'event_type' => $eventType, 'webhook_type' => $webhookType]);
        if (! is_string($webhookId) || $webhookId === '') {
            Log::error('payment_validation_failed', ['event_id' => $eventId, 'reason' => 'webhook_id_not_configured']);
            return response()->json(['status' => 'error'], 503);
        }

        try {
            if (! $this->verificationBypassed() && ! $this->verifier->verify($request, $rawBody, $webhookId)) {
                return response()->json(['status' => 'invalid'], 400);
            }
        } catch (Throwable $exception) {
            $this->logException($exception, $eventId, $eventType);
            return response()->json(['status' => 'error'], 500);
        }
        Log::info('webhook_verified', ['event_id' => $eventId, 'event_type' => $eventType]);

        $resource = $payload['resource'] ?? [];
        $payment = $this->resolver->resolve($payload, false);
        $attributes = [
            'event_type' => $eventType,
            'payment_id' => $payment?->id,
            'paypal_order_id' => is_array($resource) ? $this->captureData->extractPaypalOrderId($resource) : null,
            'capture_id' => is_array($resource) ? $this->captureData->extractCaptureId($resource) : null,
            'payload' => $payload,
            'webhook_type' => $webhookType,
            'status' => 'received',
            'received_at' => now(),
        ];

        try {
            $event = PaypalWebhookEvent::firstOrCreate(['event_id' => $eventId], $attributes);
        } catch (QueryException) {
            $event = PaypalWebhookEvent::where('event_id', $eventId)->firstOrFail();
        }

        if (! $event->wasRecentlyCreated) {
            if (in_array($event->status, ['processed', 'ignored'], true)
                || ($event->status === 'processing' && $event->updated_at?->gt(now()->subMinutes(5)))) {
                Log::info('webhook_duplicate', ['event_id' => $eventId, 'status' => $event->status]);
                return response()->json(['status' => 'duplicate']);
            }
        }

        DB::transaction(function () use ($event) {
            PaypalWebhookEvent::query()->lockForUpdate()->findOrFail($event->id)->update([
                'status' => 'processing',
                'error' => null,
                'error_message' => null,
            ]);
        }, 5);

        try {
            $status = in_array($eventType, self::SUPPORTED_EVENTS, true) ? $handler($payload) : 'ignored';
            $finalStatus = $status === 'ignored' ? 'ignored' : 'processed';
            DB::transaction(function () use ($event, $finalStatus, $payment, $resource) {
                PaypalWebhookEvent::query()->lockForUpdate()->findOrFail($event->id)->update([
                    'payment_id' => $payment?->id,
                    'paypal_order_id' => is_array($resource) ? $this->captureData->extractPaypalOrderId($resource) : null,
                    'capture_id' => is_array($resource) ? $this->captureData->extractCaptureId($resource) : null,
                    'status' => $finalStatus,
                    'error' => null,
                    'error_message' => null,
                    'processed_at' => now(),
                ]);
            }, 5);

            return response()->json(['status' => $status === 'ignored' ? 'ignored' : 'ok']);
        } catch (Throwable $exception) {
            PaypalWebhookEvent::whereKey($event->id)->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 500, ''),
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
            ]);
            Log::warning('payment_validation_failed', ['event_id' => $eventId, 'event_type' => $eventType, 'exception' => $exception::class]);
            $this->logException($exception, $eventId, $eventType);
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function verificationBypassed(): bool
    {
        return ! config('paypal.verify_webhooks', true)
            && config('paypal.allow_local_webhook_bypass', false)
            && app()->environment(['local', 'testing']);
    }

    private function logException(Throwable $exception, string $eventId, string $eventType): void
    {
        Log::error('paypal_webhook_processing_failed', [
            'exception' => $exception::class,
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
    }
}
