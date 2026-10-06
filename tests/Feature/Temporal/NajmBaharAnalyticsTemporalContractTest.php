<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmBaharAnalyticsTemporalContractTest extends TestCase
{
    public function test_analytics_controller_uses_temporal_day_boundaries_not_carbon_string_parsing(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharAnalyticsController.php'));

        $this->assertStringContainsString('parseDate(', $source);
        $this->assertStringContainsString('startOfDay(', $source);
        $this->assertStringContainsString('endOfDay(', $source);
        $this->assertStringNotContainsString('Carbon::parse(', $source);
        $this->assertStringNotContainsString('Morilog\\Jalali', $source);
    }

    public function test_analytics_presentation_uses_temporal_labels_and_components(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharAnalyticsController.php'));
        $view = file_get_contents(resource_path('views/admin/najm-bahar/analytics.blade.php'));

        $this->assertStringContainsString('$stat->date_label = $this->temporal->date(', $controller);
        $this->assertStringContainsString("'month-day'", $controller);
        $this->assertStringContainsString('<x-temporal.date-time :value="$transaction->created_at" />', $view);
        $this->assertStringContainsString('item.date_label', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
        $this->assertStringNotContainsString("toLocaleDateString('fa-IR'", $view);
    }

    public function test_legacy_analytics_view_is_scoped_into_official_temporal_runtime(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/temporal-input.js'));

        $this->assertStringContainsString("path === '/admin/najm-bahar/analytics'", $app);
        $this->assertStringContainsString('markLegacyAdminDateInputs', $runtime);
        $this->assertStringContainsString('adminDateFilterNamesForPath', $runtime);
        $this->assertStringContainsString("'/admin/najm-bahar/analytics'", $runtime);
        $this->assertStringContainsString("['date_from', 'date_to']", $runtime);
        $this->assertStringContainsString('input.dataset.calendar = calendar', $runtime);
    }

    public function test_analytics_view_uses_temporal_presentation_for_transactions_and_chart_labels(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharAnalyticsController.php'));
        $view = file_get_contents(resource_path('views/admin/najm-bahar/analytics.blade.php'));

        $this->assertStringContainsString("$item->label = $this->temporal->date(", $controller);
        $this->assertStringContainsString("'month-day'", $controller);
        $this->assertStringContainsString('<x-temporal.date-time :value="$transaction->created_at" />', $view);
        $this->assertStringContainsString('dailyData.map(item => item.label)', $view);
        $this->assertStringNotContainsString('Morilog\\\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
        $this->assertStringNotContainsString("toLocaleDateString('fa-IR'", $view);
    }
}
