<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminReportsTemporalPresentationTest extends TestCase
{
    public function test_admin_reports_index_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/reports/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Carbon\\Carbon::parse', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
