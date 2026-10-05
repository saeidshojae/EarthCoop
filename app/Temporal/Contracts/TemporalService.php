<?php

namespace App\Temporal\Contracts;

use App\Temporal\Context\TemporalContext;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeInterface;

interface TemporalService
{
    public function date(DateTimeInterface|LocalDate|string $value, ?TemporalContext $context = null, string $style = 'medium'): string;

    public function dateTime(DateTimeInterface|string $value, ?TemporalContext $context = null, string $style = 'medium'): string;

    public function time(DateTimeInterface|string $value, ?TemporalContext $context = null, string $style = 'short'): string;

    public function relative(DateTimeInterface|string $value, ?TemporalContext $context = null): string;

    public function year(DateTimeInterface|LocalDate|string $value, ?TemporalContext $context = null): int;

    public function parseDate(string $value, ?TemporalContext $context = null): LocalDate;

    public function parseDateTime(string $value, ?TemporalContext $context = null): DateTimeImmutable;

    public function parseDateParts(int $day, int $month, int $year, ?TemporalContext $context = null): LocalDate;

    public function startOfDay(LocalDate $date, ?TemporalContext $context = null): DateTimeImmutable;

    public function endOfDay(LocalDate $date, ?TemporalContext $context = null): DateTimeImmutable;
}
