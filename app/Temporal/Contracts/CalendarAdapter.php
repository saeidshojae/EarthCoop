<?php

namespace App\Temporal\Contracts;

use App\Temporal\ValueObjects\LocalDate;

interface CalendarAdapter
{
    public function id(): string;

    public function parseDate(string $value): LocalDate;

    public function formatDate(LocalDate $date, string $style, string $locale): string;

    public function year(LocalDate $date): int;
}
