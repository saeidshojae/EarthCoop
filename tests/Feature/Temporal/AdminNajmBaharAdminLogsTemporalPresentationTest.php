<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminNajmBaharAdminLogsTemporalPresentationTest extends TestCase
{
    public function test_admin_najm_bahar_admin_logs_use_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/najm-bahar/logs/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
