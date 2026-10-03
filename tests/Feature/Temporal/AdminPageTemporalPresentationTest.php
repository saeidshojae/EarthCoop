<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminPageTemporalPresentationTest extends TestCase
{
    public function test_admin_page_index_uses_temporal_date_component(): void
    {
        $path = 'resources/views/admin/pages/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date', $contents);
        $this->assertStringNotContainsString('Carbon\\Carbon::parse', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
