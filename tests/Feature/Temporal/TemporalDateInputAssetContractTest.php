<?php

namespace Tests\Feature\Temporal;

use App\Temporal\ValueObjects\LocalDate;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TemporalDateInputAssetContractTest extends TestCase
{
    public function test_persian_date_input_is_jalali_manual_first_and_progressively_enhanced(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date-input name="birth_date" :value="$date" required />',
            ['date' => LocalDate::fromCanonical('2026-10-01')],
        );

        $this->assertStringContainsString('name="birth_date"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('data-temporal-date-input', $html);
        $this->assertStringContainsString('data-calendar="jalali"', $html);
        $this->assertStringContainsString('value="۱۴۰۵/۰۷/۰۹"', $html);
        $this->assertStringContainsString('placeholder="۱۴۰۵/۰۷/۰۹"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
    }

    public function test_english_and_arabic_date_inputs_use_native_gregorian_contract(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $html = Blade::render(
                '<x-temporal.date-input name="event_date" :value="$date" />',
                ['date' => LocalDate::fromCanonical('2026-10-01')],
            );

            $this->assertStringContainsString('type="date"', $html, $locale);
            $this->assertStringContainsString('data-calendar="gregorian"', $html, $locale);
            $this->assertStringContainsString('value="2026-10-01"', $html, $locale);
        }
    }

    public function test_vite_owns_temporal_datepicker_assets_and_runtime(): void
    {
        $css = file_get_contents(resource_path('css/vite.css'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('persian-datepicker/dist/css/persian-datepicker.css', $css);
        $this->assertStringContainsString('temporal-date-input.js', $app);
        $this->assertStringNotContainsString('unpkg.com/persian-datepicker', $app);
    }
}
