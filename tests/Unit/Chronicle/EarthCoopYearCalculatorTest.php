<?php

namespace Tests\Unit\Chronicle;

use App\Chronicle\EarthCoopEpoch;
use App\Chronicle\EarthCoopYearCalculator;
use App\Temporal\ValueObjects\LocalDate;
use PHPUnit\Framework\TestCase;

class EarthCoopYearCalculatorTest extends TestCase
{
    public function test_epoch_is_first_farvardin_1401_and_first_earthcoop_year(): void
    {
        $this->assertSame('2022-03-21', EarthCoopEpoch::canonicalDate());

        $calculator = new EarthCoopYearCalculator();
        $this->assertSame(1, $calculator->yearFor(LocalDate::fromCanonical('2022-03-21')));
    }

    public function test_current_example_date_is_in_fifth_earthcoop_year(): void
    {
        $calculator = new EarthCoopYearCalculator();

        $this->assertSame(5, $calculator->yearFor(LocalDate::fromCanonical('2026-10-01')));
    }

    public function test_date_before_epoch_has_no_earthcoop_year(): void
    {
        $calculator = new EarthCoopYearCalculator();

        $this->assertNull($calculator->yearFor(LocalDate::fromCanonical('2022-03-20')));
    }

    public function test_new_earthcoop_year_begins_with_farvardin_first(): void
    {
        $calculator = new EarthCoopYearCalculator();

        $this->assertSame(1, $calculator->yearFor(LocalDate::fromCanonical('2023-03-20')));
        $this->assertSame(2, $calculator->yearFor(LocalDate::fromCanonical('2023-03-21')));
        $this->assertSame(5, $calculator->yearFor(LocalDate::fromCanonical('2027-03-20')));
        $this->assertSame(6, $calculator->yearFor(LocalDate::fromCanonical('2027-03-21')));
    }
}
