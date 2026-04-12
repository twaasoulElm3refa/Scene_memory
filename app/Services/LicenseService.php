<?php

namespace App\Services;

class LicenseService
{
    public static function generateToken($order)
    {
        $data = implode('|', [
            $order->id,
            $order->user_id,
            $order->transaction_id,
            $order->paid_at ?? now(),
            $order->user->email,
        ]);

        return hash_hmac('sha256', $data, config('app.key') . '|LICENSE_SECRET');
    }
}
