<?php

namespace Tests\Feature\Temporal;

use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TemporalDateInputAssetContractTest extends TestCase
{
    public function test_persian_date_input_is_picker_first_with_manual_fallback(): void
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
        $this->assertStringContainsString('data-temporal-picker-trigger', $html);
        $this->assertStringContainsString('aria-haspopup="dialog"', $html);
        $this->assertStringContainsString('تاریخ را از تقویم انتخاب کنید', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
    }

    public function test_false_disabled_prop_keeps_jalali_picker_trigger_visible(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date-input name="birth_date" :disabled="false" />',
        );

        $this->assertStringContainsString('data-temporal-picker-trigger', $html);
        $this->assertStringNotContainsString(' disabled', $html);
    }

    public function test_true_disabled_prop_disables_input_and_hides_picker_trigger(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date-input name="birth_date" :disabled="true" />',
        );

        $this->assertStringContainsString(' disabled', $html);
        $this->assertStringNotContainsString('data-temporal-picker-trigger', $html);
    }

    public function test_persian_date_input_preserves_already_localized_filter_value(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date-input name="date_from" :value="$value" />',
            ['value' => '۱۴۰۵/۰۷/۰۹'],
        );

        $this->assertStringContainsString('value="۱۴۰۵/۰۷/۰۹"', $html);
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

    public function test_persian_datetime_input_is_picker_first_with_time_selection(): void
    {
        app()->setLocale('fa');
        $instant = new DateTimeImmutable('2026-10-01 10:30:00', new DateTimeZone('UTC'));

        $html = Blade::render(
            '<x-temporal.date-time-input name="start_time" :value="$instant" required />',
            ['instant' => $instant],
        );

        $this->assertStringContainsString('name="start_time"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('data-temporal-datetime-input', $html);
        $this->assertStringContainsString('data-calendar="jalali"', $html);
        $this->assertStringContainsString('value="۱۴۰۵/۰۷/۰۹ ۱۰:۳۰"', $html);
        $this->assertStringContainsString('placeholder="۱۴۰۵/۰۷/۰۹ ۱۰:۳۰"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('data-temporal-picker-trigger', $html);
        $this->assertStringContainsString('انتخاب تاریخ و ساعت', $html);
    }

    public function test_persian_datetime_input_preserves_already_localized_value(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date-time-input name="start_time" :value="$value" />',
            ['value' => '۱۴۰۵/۰۷/۰۹ ۱۰:۳۰'],
        );

        $this->assertStringContainsString('value="۱۴۰۵/۰۷/۰۹ ۱۰:۳۰"', $html);
    }

    public function test_persian_datetime_input_relocalizes_canonical_value_after_validation(): void
    {
        app()->setLocale('fa');

        $html = Blade::render(
            '<x-temporal.date-time-input name="start_time" :value="$value" />',
            ['value' => '2026-10-01 10:30:00'],
        );

        $this->assertStringContainsString('value="۱۴۰۵/۰۷/۰۹ ۱۰:۳۰"', $html);
    }

    public function test_english_and_arabic_datetime_inputs_use_native_gregorian_contract(): void
    {
        $instant = new DateTimeImmutable('2026-10-01 10:30:00', new DateTimeZone('UTC'));

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $html = Blade::render(
                '<x-temporal.date-time-input name="start_time" :value="$instant" />',
                ['instant' => $instant],
            );

            $this->assertStringContainsString('type="datetime-local"', $html, $locale);
            $this->assertStringContainsString('data-calendar="gregorian"', $html, $locale);
            $this->assertStringContainsString('value="2026-10-01T10:30"', $html, $locale);
        }
    }

    public function test_vite_owns_temporal_datepicker_assets_and_runtime(): void
    {
        $pickerCss = file_get_contents(resource_path('css/temporal-picker.css'));
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/temporal-input.js'));

        $viteCss = file_get_contents(resource_path('css/vite.css'));
        $this->assertStringContainsString('persian-datepicker/dist/css/persian-datepicker.min.css', $pickerCss);
        $this->assertStringContainsString('@import "./temporal-picker.css";', $viteCss);
        $this->assertStringContainsString('import "./temporal-input.js";', $app);
        $this->assertStringNotContainsString('const loadTemporalInputRuntime', $app);
        $this->assertStringContainsString("import('persian-date')", $runtime);
        $this->assertStringContainsString("import('persian-datepicker/dist/js/persian-datepicker.min.js')", $runtime);
        $this->assertStringContainsString('data-temporal-picker-trigger', $runtime);
        $this->assertStringContainsString('timePicker', $runtime);
        $this->assertStringContainsString('MutationObserver', $runtime);
        $this->assertStringNotContainsString('unpkg.com/persian-datepicker', $app);
        $this->assertStringNotContainsString('unpkg.com/persian-datepicker', $runtime);
    }

    public function test_registration_step_one_uses_the_shared_temporal_picker(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step1.blade.php'));

        $this->assertStringContainsString("@vite(['resources/js/app.js'])", $view);
        $this->assertStringContainsString('<x-temporal.date-input', $view);
        $this->assertStringContainsString('name="birth_date"', $view);
        $this->assertStringNotContainsString('name="birth_date[]"', $view);
        $this->assertStringNotContainsString('unpkg.com/persian-datepicker', $view);
        $this->assertStringNotContainsString('vendor/persian-datepicker', $view);
    }

    public function test_profile_edit_uses_vite_jquery_for_temporal_datepicker_runtime(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $profile = file_get_contents(resource_path('views/profile/edit.blade.php'));

        $this->assertStringContainsString(
            "window.location.pathname.replace(/\\/+$/, '') === '/profile/edit'",
            $app
        );
        $this->assertStringContainsString(
            'const appJQuery = profileEditUsesLegacyJQuery ? $ : (window.jQuery || $);',
            $app
        );
        $this->assertStringContainsString("profile-assets/js/jquery.min.js", $profile);
        $this->assertStringNotContainsString("profile-assets/css/persian-datepicker.min.css", $profile);
        $this->assertStringContainsString('temporal-input.js', $app);
    }


    public function test_temporal_datepicker_owns_its_jquery_instance_instead_of_legacy_globals(): void
    {
        $runtime = file_get_contents(resource_path('js/temporal-input.js'));

        $this->assertStringContainsString("import $ from 'jquery';", $runtime);
        $this->assertStringContainsString('const temporalJQuery = $;', $runtime);
        $this->assertStringContainsString('window.jQuery = temporalJQuery;', $runtime);
        $this->assertStringContainsString('window.$ = temporalJQuery;', $runtime);
        $this->assertStringContainsString('const $input = temporalJQuery(input);', $runtime);
        $this->assertStringNotContainsString('const $input = window.jQuery(input);', $runtime);
    }

}
