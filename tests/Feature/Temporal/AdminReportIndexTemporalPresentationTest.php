<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminReportIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_report_index_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/reports/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
