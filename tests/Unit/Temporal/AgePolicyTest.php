<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Policies\AgePolicy;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AgePolicyTest extends TestCase
{
    public function test_person_is_eligible_exactly_on_minimum_age_birthday(): void
    {
        $policy = new AgePolicy();
        $today = new DateTimeImmutable('2026-10-01');

        $this->assertTrue($policy->meetsMinimumAge(LocalDate::fromCanonical('2011-10-01'), 15, $today));
    }

    public function test_person_is_not_eligible_one_day_before_minimum_age_birthday(): void
    {
        $policy = new AgePolicy();
        $today = new DateTimeImmutable('2026-10-01');

        $this->assertFalse($policy->meetsMinimumAge(LocalDate::fromCanonical('2011-10-02'), 15, $today));
    }
}
