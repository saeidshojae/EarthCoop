<?php

namespace Tests\Feature\GroupChat;

use Tests\TestCase;

class InitialRenderPerformanceContractTest extends TestCase
{
    public function test_chat_blade_does_not_issue_database_queries(): void
    {
        $source = file_get_contents(resource_path('views/groups/chat.blade.php'));

        $this->assertStringNotContainsString('\\App\\Models\\Blog::', $source);
        $this->assertStringNotContainsString('\\App\\Models\\GroupUser::', $source);
        $this->assertStringNotContainsString('\\App\\Models\\Block::', $source);
        $this->assertStringNotContainsString('$group->polls()->count()', $source);
        $this->assertStringNotContainsString('$group->userCount()', $source);
        $this->assertStringNotContainsString('$group->guestsCount()', $source);
    }

    public function test_chat_view_data_is_centralized_and_block_lookups_are_batched(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString("View::composer('groups.chat'", $source);
        $this->assertStringContainsString("->whereIn('position', ['election', 'message', 'post', 'poll'])", $source);
        $this->assertStringContainsString("SUM(CASE WHEN group_user.role = 4 THEN 1 ELSE 0 END) as guest_count", $source);
        $this->assertStringContainsString("SUM(CASE WHEN group_user.role <> 4 THEN 1 ELSE 0 END) as member_count", $source);
    }
}
