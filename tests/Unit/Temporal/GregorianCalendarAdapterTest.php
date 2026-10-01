<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GregorianCalendarAdapterTest extends TestCase
{
    public function test_gregorian_adapter_parses_canonical_date(): void
    {
        $adapter = new GregorianCalendarAdapter(new DigitNormalizer());

        $this->assertSame('2026-10-01', $adapter->parseDate('2026-10-01')->toCanonical());
    }

    public function test_gregorian_adapter_formats_short_date(): void
    {
        $adapter = new GregorianCalendarAdapter(new DigitNormalizer());

        $this->assertSame('2026-10-01', $adapter->formatDate(LocalDate::fromCanonical('2026-10-01'), 'short', 'en'));
    }

    public function test_gregorian_adapter_rejects_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GregorianCalendarAdapter(new DigitNormalizer()))->parseDate('2026-02-30');
    }
}
