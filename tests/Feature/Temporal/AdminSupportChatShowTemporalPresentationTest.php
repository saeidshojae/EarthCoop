<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminSupportChatShowTemporalPresentationTest extends TestCase
{
    public function test_admin_support_chat_show_uses_temporal_components_for_all_presented_timestamps(): void
    {
        $path = 'resources/views/admin/support-chat/show.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time :value="$message->created_at"', $contents);
        $this->assertStringContainsString('<x-temporal.date :value="$chat->created_at"', $contents);
        $this->assertStringContainsString('<x-temporal.time :value="$chat->created_at"', $contents);
        $this->assertStringContainsString('<x-temporal.date-time :value="$chat->last_activity_at"', $contents);
        $this->assertStringContainsString('<x-temporal.date-time :value="$chat->resolved_at"', $contents);

        $this->assertStringContainsString('@if($chat->last_activity_at)', $contents);
        $this->assertStringContainsString('@if($chat->resolved_at)', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
