<?php

namespace App\Chronicle;

use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;

final class EarthCoopYearCalculator
{
    private readonly JalaliCalendarAdapter $jalali;

    public function __construct(?JalaliCalendarAdapter $jalali = null)
    {
        $this->jalali = $jalali ?? new JalaliCalendarAdapter(new DigitNormalizer());
    }

    public function yearFor(LocalDate $date): ?int
    {
        if ($date->toCanonical() < EarthCoopEpoch::canonicalDate()) {
            return null;
        }

        $jalaliDate = $this->jalali->formatDate($date, 'short', 'fa');
        $jalaliYear = (int) substr($jalaliDate, 0, 4);

        return $jalaliYear - 1400;
    }
}
