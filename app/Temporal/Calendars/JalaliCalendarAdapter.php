<?php

namespace App\Temporal\Calendars;

use App\Temporal\Contracts\CalendarAdapter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;
use InvalidArgumentException;
use Morilog\Jalali\CalendarUtils;

final class JalaliCalendarAdapter implements CalendarAdapter
{
    public function __construct(private readonly DigitNormalizer $digits)
    {
    }

    public function id(): string
    {
        return 'jalali';
    }

    public function parseDate(string $value): LocalDate
    {
        $normalized = $this->digits->toLatin(trim($value));

        if (!preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Jalali date must use YYYY/MM/DD format.');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if (!CalendarUtils::checkDate($year, $month, $day, true)) {
            throw new InvalidArgumentException('Invalid Jalali civil date.');
        }

        [$gregorianYear, $gregorianMonth, $gregorianDay] = CalendarUtils::toGregorian($year, $month, $day);

        return LocalDate::fromCanonical(sprintf(
            '%04d-%02d-%02d',
            $gregorianYear,
            $gregorianMonth,
            $gregorianDay,
        ));
    }

    public function formatDate(LocalDate $date, string $style, string $locale): string
    {
        if ($style !== 'short') {
            throw new InvalidArgumentException("Unsupported Jalali date style: {$style}");
        }

        [$year, $month, $day] = CalendarUtils::toJalali($date->year(), $date->month(), $date->day());

        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }
}
