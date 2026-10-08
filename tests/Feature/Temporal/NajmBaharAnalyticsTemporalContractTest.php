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
        $this->assertStringNotContainsString('$item->label = $this->temporal->date(', $controller);
        $this->assertStringContainsString("'month-day'", $controller);
        $this->assertStringContainsString('<x-temporal.date-time :value="$transaction->created_at" />', $view);
        $this->assertStringContainsString('item.date_label', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
        $this->assertStringNotContainsString("toLocaleDateString('fa-IR'", $view);
    }

    public function test_analytics_filters_use_shared_temporal_date_components_without_path_shims(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-bahar/analytics.blade.php'));
        $runtime = file_get_contents(resource_path('js/temporal-input.js'));

        $this->assertStringContainsString('<x-temporal.date-input name="date_from"', $view);
        $this->assertStringContainsString('<x-temporal.date-input name="date_to"', $view);
        $this->assertStringNotContainsString('markLegacyAdminDateInputs', $runtime);
        $this->assertStringNotContainsString('adminDateFilterNamesForPath', $runtime);
    }

}
