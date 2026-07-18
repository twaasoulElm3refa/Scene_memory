<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Services\PayPalWalletServices;
use App\Services\PayPalWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletWebhookController extends Controller
{
    public function __construct(
        private readonly PayPalWebhookProcessor $processor,
        private readonly PayPalWalletServices $paypal,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        return $this->processor->process(
            $request,
            config('paypal.wallet_webhook_id'),
            'wallet',
            fn (array $payload) => $this->paypal->handleWebhook($payload),
        );
    }
}
