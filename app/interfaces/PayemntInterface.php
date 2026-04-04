<?php

namespace App\Interfaces;


interface PaymentInterface
{
    public function pay(array $data): string;   // returns redirect URL
    public function success(string $token): array;
    public function cancel(): array;
}
