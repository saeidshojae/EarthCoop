<?php

namespace App\Temporal\Calendars;

use App\Temporal\Contracts\CalendarAdapter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;
use Morilog\Jalali\CalendarUtils;

final class JalaliCalendarAdapter implements CalendarAdapter
{
    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    private const WEEKDAYS = [
        0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه',
        4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه',
    ];

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

        if (! preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Jalali date must use YYYY/MM/DD format.');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if (! CalendarUtils::checkDate($year, $month, $day, true)) {
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
        [$year, $month, $day] = CalendarUtils::toJalali($date->year(), $date->month(), $date->day());

        return match ($style) {
            'short' => sprintf('%04d/%02d/%02d', $year, $month, $day),
            'medium' => sprintf('%d %s %d', $day, self::MONTHS[$month], $year),
            'long' => sprintf(
                '%s %d %s %d',
                self::WEEKDAYS[(int) (new DateTimeImmutable($date->toCanonical()))->format('w')],
                $day,
                self::MONTHS[$month],
                $year,
            ),
            default => throw new InvalidArgumentException("Unsupported Jalali date style: {$style}"),
        };
    }

    public function year(LocalDate $date): int
    {
        [$year] = CalendarUtils::toJalali($date->year(), $date->month(), $date->day());

        return $year;
    }
}
