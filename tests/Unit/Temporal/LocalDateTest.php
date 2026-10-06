<?php

namespace Tests\Unit\Temporal;

use App\Temporal\ValueObjects\LocalDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LocalDateTest extends TestCase
{
    public function test_local_date_round_trips_canonical_value(): void
    {
        $date = LocalDate::fromCanonical('2026-10-01');

        $this->assertSame(2026, $date->year());
        $this->assertSame(10, $date->month());
        $this->assertSame(1, $date->day());
        $this->assertSame('2026-10-01', $date->toCanonical());
    }

    public function test_invalid_canonical_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalDate::fromCanonical('2026-02-30');
    }

    public function test_non_canonical_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalDate::fromCanonical('2026/10/01');
    }
}
