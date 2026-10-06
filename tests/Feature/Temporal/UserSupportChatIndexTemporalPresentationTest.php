<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class UserSupportChatIndexTemporalPresentationTest extends TestCase
{
    public function test_user_support_chat_message_time_uses_shared_temporal_component(): void
    {
        $contents = file_get_contents(resource_path('views/user/support-chat/index.blade.php'));

        $this->assertSame(1, substr_count($contents, '<x-temporal.time :value="$message->created_at"'));
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
