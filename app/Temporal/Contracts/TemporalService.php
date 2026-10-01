<?php

namespace App\Temporal\Contracts;

use App\Temporal\Context\TemporalContext;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeInterface;

interface TemporalService
{
    public function date(DateTimeInterface|LocalDate|string $value, ?TemporalContext $context = null, string $style = 'medium'): string;

    public function dateTime(DateTimeInterface|string $value, ?TemporalContext $context = null, string $style = 'medium'): string;

    public function relative(DateTimeInterface|string $value, ?TemporalContext $context = null): string;

    public function parseDate(string $value, ?TemporalContext $context = null): LocalDate;

    public function parseDateParts(int $day, int $month, int $year, ?TemporalContext $context = null): LocalDate;
}
