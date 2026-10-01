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
}
