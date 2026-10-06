<?php

namespace Tests\Feature\Temporal;

use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TemporalBladeComponentsTest extends TestCase
{
    public function test_date_component_renders_localized_human_text_with_machine_date(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date :value="$date" style="medium" />',
            ['date' => LocalDate::fromCanonical('2026-10-01')],
        );

        $this->assertStringContainsString('datetime="2026-10-01"', $html);
        $this->assertStringContainsString('۹ مهر ۱۴۰۵', $html);
    }

    public function test_date_component_follows_active_english_locale(): void
    {
        app()->setLocale('en');

        $html = Blade::render(
            '<x-temporal.date :value="$date" style="medium" />',
            ['date' => LocalDate::fromCanonical('2026-10-01')],
        );

        $this->assertStringContainsString('datetime="2026-10-01"', $html);
        $this->assertStringContainsString('Oct 1, 2026', $html);
    }

    public function test_datetime_component_keeps_utc_machine_value_and_localizes_visible_value(): void
    {
        app()->setLocale('en');

        $html = Blade::render(
            '<x-temporal.date-time :value="$instant" style="medium" />',
            ['instant' => new DateTimeImmutable('2026-10-01T13:30:00Z')],
        );

        $this->assertStringContainsString('datetime="2026-10-01T13:30:00Z"', $html);
        $this->assertStringContainsString('Oct 1, 2026 13:30', $html);
    }

    public function test_relative_component_renders_persian_relative_text_with_utc_machine_value(): void
    {
        app()->setLocale('fa');
        $instant = new DateTimeImmutable('-5 minutes');

        $html = Blade::render(
            '<x-temporal.relative :value="$instant" />',
            ['instant' => $instant],
        );

        $expectedMachineValue = $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $this->assertStringContainsString('datetime="' . $expectedMachineValue . '"', $html);
        $this->assertStringContainsString('۵ دقیقه پیش', $html);
    }

    public function test_relative_component_follows_active_english_locale(): void
    {
        app()->setLocale('en');
        $instant = new DateTimeImmutable('-5 minutes');

        $html = Blade::render(
            '<x-temporal.relative :value="$instant" />',
            ['instant' => $instant],
        );

        $this->assertStringContainsString('5 minutes ago', $html);
    }
}
