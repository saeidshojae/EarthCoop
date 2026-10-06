<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminReportShowTemporalPresentationTest extends TestCase
{
    public function test_admin_report_show_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/reports/show.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
