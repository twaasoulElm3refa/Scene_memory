<?php

namespace App\Services;

use InvalidArgumentException;

class GiftCommission
{
    public function split(int $gross): array
    {
        if ($gross < 0 || $gross > config('virtual_gifts.max_gross_minor', 99999999)) {
            throw new InvalidArgumentException('Gift amount is out of range.');
        }
        // Half-up, once on gross; no floats or per-unit rounding.
        $commission = intdiv($gross * 15 + 50, 100);

        return ['commission' => $commission, 'recipient' => $gross - $commission];
    }
}
