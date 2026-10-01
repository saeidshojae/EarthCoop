<?php

namespace App\Temporal\Calendars;

use App\Temporal\Contracts\CalendarAdapter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;
use InvalidArgumentException;

final class GregorianCalendarAdapter implements CalendarAdapter
{
    public function __construct(private readonly DigitNormalizer $digits)
    {
    }

    public function id(): string
    {
        return 'gregorian';
    }

    public function parseDate(string $value): LocalDate
    {
        return LocalDate::fromCanonical($this->digits->toLatin(trim($value)));
    }

    public function formatDate(LocalDate $date, string $style, string $locale): string
    {
        if ($style !== 'short') {
            throw new InvalidArgumentException("Unsupported Gregorian date style: {$style}");
        }

        return $date->toCanonical();
    }
}
