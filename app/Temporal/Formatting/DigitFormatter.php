<?php

namespace App\Temporal\Formatting;

final class DigitFormatter
{
    private const LATIN_TO_PERSIAN = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ];

    public function format(string $value, string $numberingSystem): string
    {
        return $numberingSystem === 'persian'
            ? strtr($value, self::LATIN_TO_PERSIAN)
            : $value;
    }
}
