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
}
