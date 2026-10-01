<?php

namespace App\Temporal\Formatting;

final class DigitNormalizer
{
    private const PERSIAN_TO_LATIN = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    private const ARABIC_INDIC_TO_LATIN = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public function toLatin(string $value): string
    {
        return strtr($value, self::PERSIAN_TO_LATIN + self::ARABIC_INDIC_TO_LATIN);
    }
}
