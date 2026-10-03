<?php

namespace App\Temporal\Context;

use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final readonly class TemporalContext
{
    private function __construct(
        private string $locale,
        private string $calendar,
        private string $timezone,
        private string $numberingSystem,
    ) {
    }

    public static function create(
        string $locale,
        string $calendar,
        string $timezone,
        string $numberingSystem,
    ): self {
        try {
            new DateTimeZone($timezone);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("Invalid timezone: {$timezone}", previous: $exception);
        }

        return new self($locale, $calendar, $timezone, $numberingSystem);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function calendar(): string
    {
        return $this->calendar;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function numberingSystem(): string
    {
        return $this->numberingSystem;
    }
}
