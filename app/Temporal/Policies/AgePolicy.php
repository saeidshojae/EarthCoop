<?php

namespace App\Temporal\Policies;

use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;

final class AgePolicy
{
    public function meetsMinimumAge(
        LocalDate $birthDate,
        int $minimumAge,
        ?DateTimeImmutable $today = null,
    ): bool {
        $today ??= new DateTimeImmutable('today');
        $cutoff = $today->modify("-{$minimumAge} years")->format('Y-m-d');

        return $birthDate->toCanonical() <= $cutoff;
    }
}
