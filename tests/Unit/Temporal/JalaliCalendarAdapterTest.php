<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JalaliCalendarAdapterTest extends TestCase
{
    public function test_jalali_adapter_converts_known_date_to_canonical_gregorian(): void
    {
        $adapter = new JalaliCalendarAdapter(new DigitNormalizer());

        $this->assertSame('2026-10-01', $adapter->parseDate('1405/07/09')->toCanonical());
    }

    public function test_jalali_adapter_accepts_persian_digits(): void
    {
        $adapter = new JalaliCalendarAdapter(new DigitNormalizer());

        $this->assertSame('2026-10-01', $adapter->parseDate('۱۴۰۵/۰۷/۰۹')->toCanonical());
    }

    public function test_jalali_adapter_formats_known_canonical_date(): void
    {
        $adapter = new JalaliCalendarAdapter(new DigitNormalizer());

        $this->assertSame('1405/07/09', $adapter->formatDate(LocalDate::fromCanonical('2026-10-01'), 'short', 'fa'));
    }

    public function test_jalali_leap_day_round_trips(): void
    {
        $adapter = new JalaliCalendarAdapter(new DigitNormalizer());
        $canonical = $adapter->parseDate('1399/12/30');

        $this->assertSame('1399/12/30', $adapter->formatDate($canonical, 'short', 'fa'));
    }

    public function test_invalid_jalali_leap_day_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new JalaliCalendarAdapter(new DigitNormalizer()))->parseDate('1400/12/30');
    }
}
