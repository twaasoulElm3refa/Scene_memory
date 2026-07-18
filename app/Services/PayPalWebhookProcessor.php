<?php

namespace App\Services;

use App\Models\PaypalWebhookEvent;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function __construct(private readonly PayPalWebhookVerifier $verifier) {}

    public function process(
        Request $request,
        ?string $webhookId,
        string $webhookType,
        Closure $handler,
    ): JsonResponse {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        $eventId = is_array($payload) ? ($payload['id'] ?? null) : null;
        $eventType = is_array($payload) ? ($payload['event_type'] ?? null) : null;

        Log::info('PayPal webhook received', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'webhook_route' => $request->path(),
            'timestamp' => now()->toIso8601String(),
        ]);

        if (! is_array($payload)) {
            return response()->json(['status' => 'invalid'], 400);
        }

        if (! $webhookId) {
            Log::error('PayPal webhook ID is not configured', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'webhook_type' => $webhookType,
            ]);

            return response()->json(['status' => 'error'], 500);
        }

        try {
            if (! $this->verifier->verify($request, $rawBody, $webhookId)) {
                return response()->json(['status' => 'invalid'], 400);
            }
        } catch (Throwable $exception) {
            $this->logException($exception, $eventId, $eventType);

            return response()->json(['status' => 'error'], 500);
        }

        if (! is_string($eventId) || $eventId === '' || ! is_string($eventType) || $eventType === '') {
            return response()->json(['status' => 'invalid'], 400);
        }

        $event = PaypalWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'webhook_type' => $webhookType,
                'status' => 'processing',
            ],
        );

        if (! $event->wasRecentlyCreated && in_array($event->status, ['processing', 'processed'], true)) {
            return response()->json(['status' => 'duplicate']);
        }

        if ($event->status === 'failed') {
            $event->update(['status' => 'processing', 'error' => null]);
        }

        try {
            $status = DB::transaction(function () use ($event, $eventType, $handler, $payload) {
                $status = in_array($eventType, self::SUPPORTED_EVENTS, true)
                    ? $handler($payload)
                    : 'ignored';

                $event->update([
                    'status' => 'processed',
                    'error' => null,
                    'processed_at' => now(),
                ]);

                return in_array($status, ['ok', 'ignored'], true) ? $status : 'ok';
            });

            return response()->json(['status' => $status]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 500, ''),
            ]);
            $this->logException($exception, $eventId, $eventType);

            return response()->json(['status' => 'error'], 500);
        }
    }

    private function logException(Throwable $exception, ?string $eventId, ?string $eventType): void
    {
        Log::error('PayPal webhook processing failed', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
    }
}
