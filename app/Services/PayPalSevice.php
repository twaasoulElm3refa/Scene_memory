<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Exception;

class PayPalService implements PaymentInterface
{
    protected PayPalClient $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
        $this->provider->getAccessToken();
    }

    /**
     * إنشاء أوردر وإرجاع رابط الـ PayPal
     */
    public function pay(array $data): string
    {
        $order = $this->provider->createOrder([
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => route('paypal.success'),
                'cancel_url' => route('paypal.cancel'),
            ],
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => config('paypal.currency', 'USD'),
                        'value'         => number_format($data['amount'], 2, '.', ''),
                    ],
                    'description' => $data['description'] ?? 'Order Payment',
                ],
            ],
        ]);

        if (isset($order['id']) && $order['status'] === 'CREATED') {
            foreach ($order['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return $link['href']; // redirect URL
                }
            }
        }

        throw new Exception('PayPal order creation failed: ' . json_encode($order));
    }

    /**
     * التقاط الدفع بعد موافقة المستخدم
     */
    public function success(string $token): array
    {
        $result = $this->provider->capturePaymentOrder($token);

        if (isset($result['status']) && $result['status'] === 'COMPLETED') {
            $capture = $result['purchase_units'][0]['payments']['captures'][0];

            return [
                'success'        => true,
                'transaction_id' => $capture['id'],
                'amount'         => $capture['amount']['value'],
                'currency'       => $capture['amount']['currency_code'],
                'payer_email'    => $result['payer']['email_address'] ?? null,
                'status'         => $result['status'],
            ];
        }

        throw new Exception('Payment capture failed: ' . json_encode($result));
    }

    /**
     * المستخدم ألغى العملية
     */
    public function cancel(): array
    {
        return [
            'success' => false,
            'message' => 'Payment was cancelled by the user.',
        ];
    }
}
