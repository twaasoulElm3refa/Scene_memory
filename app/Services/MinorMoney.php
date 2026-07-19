<?php

namespace App\Services;

use InvalidArgumentException;

class MinorMoney
{
    public function fromDecimal(mixed $value): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Amount must have at most two decimal places.');
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');

        if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) {
            throw new InvalidArgumentException('Amount is too large.');
        }

        return ($whole * 100) + $fraction;
    }

    public function toDecimal(int $minor): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $value = intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$value : $value;
    }
}
