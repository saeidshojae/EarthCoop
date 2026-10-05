<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminWelcomeIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_welcome_index_uses_shared_temporal_date_time_component(): void
    {
        $path = 'resources/views/admin/welcome/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $component = '<x-temporal.date-time :value="$date" />';

        $this->assertSame(1, substr_count($contents, $component));
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
