<?php

namespace App\Modules\Stock\Pricing;

use InvalidArgumentException;
use OverflowException;

final class BaharGolConverter
{
    public const GOL_PER_BAHAR = 100;

    public function toGol(string $bahar): int
    {
        $value = trim($bahar);

        if ($value === '') {
            throw new InvalidArgumentException('Bahar value is required.');
        }

        if (preg_match('/^\d+\.\d{3,}$/', $value) === 1) {
            throw new InvalidArgumentException('Bahar supports at most two decimal places.');
        }

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Bahar must be a positive decimal value.');
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        $golDigits = ltrim($whole . $fraction, '0');
        $golDigits = $golDigits === '' ? '0' : $golDigits;

        if ($golDigits === '0') {
            throw new InvalidArgumentException('Bahar must be greater than zero.');
        }

        $max = (string) PHP_INT_MAX;
        if (strlen($golDigits) > strlen($max)
            || (strlen($golDigits) === strlen($max) && strcmp($golDigits, $max) > 0)) {
            throw new OverflowException('Bahar value exceeds the supported Gol integer range.');
        }

        return (int) $golDigits;
    }

    public function toBaharString(int $gol): string
    {
        if ($gol < 0) {
            throw new InvalidArgumentException('Gol value cannot be negative.');
        }

        $whole = intdiv($gol, self::GOL_PER_BAHAR);
        $fraction = $gol % self::GOL_PER_BAHAR;

        if ($fraction === 0) {
            return (string) $whole;
        }

        $fractionText = rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');

        return $whole . '.' . $fractionText;
    }
}
