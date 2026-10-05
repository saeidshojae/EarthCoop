<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminSupportChatIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_support_chat_index_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/support-chat/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
        $this->assertStringContainsString('@if($chat->last_activity_at)', $contents);
    }
}
