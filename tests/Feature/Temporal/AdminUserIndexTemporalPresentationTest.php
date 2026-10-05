<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminUserIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_user_index_uses_shared_temporal_date_and_time_components(): void
    {
        $path = 'resources/views/admin/user/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date :value="$createdAt" />', $contents);
        $this->assertStringContainsString('<x-temporal.time :value="$createdAt" />', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
