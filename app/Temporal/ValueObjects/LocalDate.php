<?php

namespace App\Temporal\ValueObjects;

use InvalidArgumentException;

final readonly class LocalDate
{
    private function __construct(
        private int $year,
        private int $month,
        private int $day,
    ) {
    }

    public static function fromCanonical(string $value): self
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            throw new InvalidArgumentException('LocalDate must use canonical YYYY-MM-DD format.');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if (!checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('LocalDate is not a valid Gregorian civil date.');
        }

        return new self($year, $month, $day);
    }

    public function year(): int
    {
        return $this->year;
    }

    public function month(): int
    {
        return $this->month;
    }

    public function day(): int
    {
        return $this->day;
    }

    public function toCanonical(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
