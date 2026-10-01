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

    public function test_gregorian_adapter_formats_supported_styles(): void
    {
        $adapter = new GregorianCalendarAdapter(new DigitNormalizer());
        $date = LocalDate::fromCanonical('2026-10-01');

        $this->assertSame('2026-10-01', $adapter->formatDate($date, 'short', 'en'));
        $this->assertSame('Oct 1, 2026', $adapter->formatDate($date, 'medium', 'en'));
        $this->assertSame('Thursday, October 1, 2026', $adapter->formatDate($date, 'long', 'en'));
    }

    public function test_gregorian_adapter_rejects_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GregorianCalendarAdapter(new DigitNormalizer()))->parseDate('2026-02-30');
    }
}
