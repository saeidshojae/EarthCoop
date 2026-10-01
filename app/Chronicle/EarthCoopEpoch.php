<?php

namespace App\Chronicle;

final class EarthCoopEpoch
{
    /**
     * 1 Farvardin 1401 SH = 21 March 2022 Gregorian.
     *
     * This is a constitutional historical constant, not an admin setting.
     */
    public const CANONICAL_DATE = '2022-03-21';

    private function __construct()
    {
    }

    public static function canonicalDate(): string
    {
        return self::CANONICAL_DATE;
    }
}
