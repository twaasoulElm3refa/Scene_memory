<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Services\PayPalServices;
use App\Services\PayPalWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PayPalWebhookProcessor $processor,
        private readonly PayPalServices $paypal,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        return $this->processor->process(
            $request,
            config('paypal.webhooks.checkout'),
            'checkout',
            fn (array $payload) => $this->paypal->handleWebhook($payload),
        );
    }
}
