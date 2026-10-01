<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\TemporalManager;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TemporalManagerTest extends TestCase
{
    private function manager(): array
    {
        $resolver = new TemporalContextResolver(
            ['fa' => 'jalali', 'en' => 'gregorian', 'ar' => 'gregorian'],
            'gregorian',
            'UTC',
            'fa',
        );
        $digits = new DigitNormalizer();

        return [
            new TemporalManager(
                new GregorianCalendarAdapter($digits),
                new JalaliCalendarAdapter($digits),
                $resolver,
            ),
            $resolver,
        ];
    }

    public function test_manager_parses_and_formats_using_context_calendar_and_numbering(): void
    {
        [$manager, $resolver] = $this->manager();
        $fa = $resolver->forLocale('fa', 'Asia/Tehran');
        $en = $resolver->forLocale('en', 'Europe/London');

        $this->assertSame('2026-10-01', $manager->parseDate('1405/07/09', $fa)->toCanonical());
        $this->assertSame('۱۴۰۵/۰۷/۰۹', $manager->date(LocalDate::fromCanonical('2026-10-01'), $fa, 'short'));
        $this->assertSame('۹ مهر ۱۴۰۵', $manager->date(LocalDate::fromCanonical('2026-10-01'), $fa, 'medium'));
        $this->assertSame('2026-10-01', $manager->date(LocalDate::fromCanonical('2026-10-01'), $en, 'short'));
    }

    public function test_manager_formats_time_only_in_context_timezone_and_numbering(): void
    {
        [$manager, $resolver] = $this->manager();
        $instant = new DateTimeImmutable('2026-10-01T13:30:45Z');

        $this->assertSame('۱۳:۳۰', $manager->time($instant, $resolver->forLocale('fa', 'UTC'), 'short'));
        $this->assertSame('13:30:45', $manager->time($instant, $resolver->forLocale('en', 'UTC'), 'long'));
        $this->assertSame('03:30', $manager->time($instant, $resolver->forLocale('en', 'Pacific/Honolulu'), 'short'));
    }

    public function test_manager_assembles_date_parts_using_context_calendar(): void
    {
        [$manager, $resolver] = $this->manager();

        $this->assertSame(
            '2026-10-01',
            $manager->parseDateParts(9, 7, 1405, $resolver->forLocale('fa'))->toCanonical(),
        );
        $this->assertSame(
            '2026-10-01',
            $manager->parseDateParts(1, 10, 2026, $resolver->forLocale('en'))->toCanonical(),
        );
    }

    public function test_same_instant_can_resolve_to_different_civil_dates_by_timezone(): void
    {
        [$manager, $resolver] = $this->manager();
        $instant = new DateTimeImmutable('2026-10-01T23:30:00Z');

        $this->assertSame('2026-10-02', $manager->date($instant, $resolver->forLocale('en', 'Pacific/Kiritimati'), 'short'));
        $this->assertSame('2026-10-01', $manager->date($instant, $resolver->forLocale('en', 'America/Los_Angeles'), 'short'));
    }
}
