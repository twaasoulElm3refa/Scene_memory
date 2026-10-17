<?php

namespace App\Services;

use InvalidArgumentException;

class GiftProviderFeeMoney
{
    public function parse(string $value, string $currency): array
    {
        if (in_array($currency, ['JPY', 'HUF', 'TWD'], true)) {
            if (! preg_match('/^\d+(?:\.0{1,2})?$/', $value) || strlen(explode('.', $value)[0]) > 15) {
                throw new InvalidArgumentException('Invalid zero-decimal provider fee.');
            }

            return ['amount' => (int) $value, 'precision' => 0];
        }
        if (! in_array($currency, array_unique(array_merge(config('virtual_gifts.currencies'), config('virtual_gifts.paypal_currencies'))), true)) {
            throw new InvalidArgumentException('Unsupported provider fee currency.');
        }

        return ['amount' => app(MinorMoney::class)->fromDecimal($value), 'precision' => 2];
    }
}
