<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminUserShowTemporalPresentationTest extends TestCase
{
    public function test_admin_user_show_uses_shared_temporal_date_time_components(): void
    {
        $path = 'resources/views/admin/user/show.blade.php';
        $contents = file_get_contents(base_path($path));

        foreach ([
            '$createdAt',
            '$updatedAt',
            '$lastSeen',
            '$emailVerifiedAt',
            '$lastLoginAt',
            '$lastActivity',
        ] as $value) {
            $component = '<x-temporal.date-time :value="' . $value . '" />';
            $this->assertSame(1, substr_count($contents, $component), "Expected exactly one shared temporal date-time component for {$value}.");
        }

        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
