<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Services\PayPalServices;
use App\Services\PayPalWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PayPalWebhookProcessor $processor,
        private readonly PayPalServices $paypal,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('SCEMORY_WEBHOOK_ENDPOINT_REACHED', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'event_id' => $request->input('id'),
            'event_type' => $request->input('event_type'),
            'has_transmission_id' => filled($request->header('PAYPAL-TRANSMISSION-ID')),
            'has_transmission_sig' => filled($request->header('PAYPAL-TRANSMISSION-SIG')),
            'webhook_type' => 'checkout',
        ]);

        return $this->processor->process(
            $request,
            config('paypal.webhook_id'),
            'checkout',
            fn (array $payload) => $this->paypal->handleWebhook($payload),
        );
    }
}
