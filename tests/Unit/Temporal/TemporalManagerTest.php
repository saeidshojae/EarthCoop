<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\TemporalManager;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
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
        $this->assertSame('۹ مهر', $manager->date(LocalDate::fromCanonical('2026-10-01'), $fa, 'month-day'));
        $this->assertSame('2026-10-01', $manager->date(LocalDate::fromCanonical('2026-10-01'), $en, 'short'));
        $this->assertSame('Oct 1', $manager->date(LocalDate::fromCanonical('2026-10-01'), $en, 'month-day'));
    }

    public function test_manager_exposes_year_in_context_calendar_without_formatting_round_trip(): void
    {
        [$manager, $resolver] = $this->manager();
        $instant = new DateTimeImmutable('2026-10-01T12:00:00Z');

        $this->assertSame(1405, $manager->year($instant, $resolver->forLocale('fa', 'Asia/Tehran')));
        $this->assertSame(2026, $manager->year($instant, $resolver->forLocale('en', 'UTC')));
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

    public function test_manager_parses_localized_datetime_into_canonical_utc_instant(): void
    {
        [$manager, $resolver] = $this->manager();

        $persian = $manager->parseDateTime('۱۴۰۵/۰۷/۰۹ ۱۸:۰۰', $resolver->forLocale('fa', 'Asia/Tehran'));
        $english = $manager->parseDateTime('2026-10-01 18:00', $resolver->forLocale('en', 'Europe/London'));

        $this->assertSame('2026-10-01T14:30:00+00:00', $persian->format('c'));
        $this->assertSame('2026-10-01T17:00:00+00:00', $english->format('c'));
    }

    public function test_manager_accepts_canonical_machine_datetimes_independent_of_display_calendar(): void
    {
        [$manager, $resolver] = $this->manager();
        $fa = $resolver->forLocale('fa', 'Asia/Tehran');

        $canonicalLocal = $manager->parseDateTime('2026-10-01 18:00:00', $fa);
        $isoInstant = $manager->parseDateTime('2026-10-01T18:00:00+03:30', $fa);

        $this->assertSame('2026-10-01T14:30:00+00:00', $canonicalLocal->format('c'));
        $this->assertSame('2026-10-01T14:30:00+00:00', $isoInstant->format('c'));
    }

    public function test_manager_rejects_invalid_local_time(): void
    {
        [$manager, $resolver] = $this->manager();
        $this->expectException(InvalidArgumentException::class);

        $manager->parseDateTime('1405/07/09 25:00', $resolver->forLocale('fa', 'Asia/Tehran'));
    }

    public function test_local_day_boundaries_are_converted_to_utc_from_context_timezone(): void
    {
        [$manager, $resolver] = $this->manager();
        $context = $resolver->forLocale('fa', 'Asia/Tehran');
        $date = LocalDate::fromCanonical('2026-10-01');

        $this->assertSame('2026-09-30T20:30:00+00:00', $manager->startOfDay($date, $context)->format('c'));
        $this->assertSame('2026-10-01T20:29:59+00:00', $manager->endOfDay($date, $context)->format('c'));
    }

    public function test_same_instant_can_resolve_to_different_civil_dates_by_timezone(): void
    {
        [$manager, $resolver] = $this->manager();
        $instant = new DateTimeImmutable('2026-10-01T23:30:00Z');

        $this->assertSame('2026-10-02', $manager->date($instant, $resolver->forLocale('en', 'Pacific/Kiritimati'), 'short'));
        $this->assertSame('2026-10-01', $manager->date($instant, $resolver->forLocale('en', 'America/Los_Angeles'), 'short'));
    }

    public function test_english_relative_time_uses_singular_and_plural_units_correctly(): void
    {
        [$manager, $resolver] = $this->manager();
        $context = $resolver->forLocale('en', 'UTC');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->assertSame('in 1 hour', $manager->relative($now->modify('+61 minutes'), $context));
        $this->assertSame('2 hours ago', $manager->relative($now->modify('-121 minutes'), $context));
    }
}
