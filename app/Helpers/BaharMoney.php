<?php

namespace App\Helpers;

use Illuminate\Support\HtmlString;

class BaharMoney
{
    public const GOL_PER_BAHAR = 100;

    public static function toGolFromBahar(int $bahar, int $gol = 0): int
    {
        return ($bahar * self::GOL_PER_BAHAR) + $gol;
    }

    public static function parseToGol(string|int|float $value): int
    {
        if (is_int($value)) {
            return $value * self::GOL_PER_BAHAR;
        }

        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Invalid amount format.');
        }

        if (strpos($value, '.') === false) {
            return ((int) $value) * self::GOL_PER_BAHAR;
        }

        [$bahar, $gol] = explode('.', $value, 2);
        $gol = str_pad($gol, 2, '0');

        return ((int) $bahar) * self::GOL_PER_BAHAR + (int) $gol;
    }

    public static function format(int $golAmount): string
    {
        $isNegative = $golAmount < 0;
        $golAmount = abs($golAmount);

        $bahar = intdiv($golAmount, self::GOL_PER_BAHAR);
        $gol = $golAmount % self::GOL_PER_BAHAR;

        $parts = [];
        if ($bahar > 0) {
            $parts[] = number_format($bahar) . ' بهار';
        }

        if ($gol > 0 || $bahar === 0) {
            $parts[] = number_format($gol) . ' گل';
        }

        $formatted = implode(' و ', $parts);

        return $isNegative ? '-' . $formatted : $formatted;
    }

    public static function formatDecimal(int $golAmount): string
    {
        $isNegative = $golAmount < 0;
        $golAmount = abs($golAmount);

        $bahar = intdiv($golAmount, self::GOL_PER_BAHAR);
        $gol = $golAmount % self::GOL_PER_BAHAR;

        $formatted = number_format($bahar) . '.' . str_pad((string) $gol, 2, '0', STR_PAD_LEFT) . ' بهار';

        return $isNegative ? '-' . $formatted : $formatted;
    }

    public static function formatDecimalHtml(int $golAmount): HtmlString
    {
        $isNegative = $golAmount < 0;
        $golAmount = abs($golAmount);

        $bahar = intdiv($golAmount, self::GOL_PER_BAHAR);
        $gol = $golAmount % self::GOL_PER_BAHAR;

        $sign = $isNegative ? '-' : '';
        $baharFormatted = $sign . number_format($bahar);
        $golFormatted = str_pad((string) $gol, 2, '0', STR_PAD_LEFT);

        $html = '<span class="bahar-amount" style="white-space: nowrap;">'
            . '<span class="bahar-main">' . $baharFormatted . '</span>'
            . '<span class="bahar-decimal" style="font-size: 0.75em; opacity: 0.8;">.' . $golFormatted . '</span>'
            . ' بهار</span>';

        return new HtmlString($html);
    }

    public static function formatDecimalValue(int $golAmount): string
    {
        $isNegative = $golAmount < 0;
        $golAmount = abs($golAmount);

        $bahar = intdiv($golAmount, self::GOL_PER_BAHAR);
        $gol = $golAmount % self::GOL_PER_BAHAR;

        $formatted = $bahar . '.' . str_pad((string) $gol, 2, '0', STR_PAD_LEFT);

        return $isNegative ? '-' . $formatted : $formatted;
    }

    public static function formatDecimalValueHtml(int $golAmount): HtmlString
    {
        $isNegative = $golAmount < 0;
        $golAmount = abs($golAmount);

        $bahar = intdiv($golAmount, self::GOL_PER_BAHAR);
        $gol = $golAmount % self::GOL_PER_BAHAR;

        $sign = $isNegative ? '-' : '';
        $baharFormatted = $sign . $bahar;
        $golFormatted = str_pad((string) $gol, 2, '0', STR_PAD_LEFT);

        $html = '<span class="bahar-amount" style="white-space: nowrap;">'
            . '<span class="bahar-main">' . $baharFormatted . '</span>'
            . '<span class="bahar-decimal" style="font-size: 0.75em; opacity: 0.8;">.' . $golFormatted . '</span>'
            . '</span>';

        return new HtmlString($html);
    }
}
