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

    public function test_controller_reuses_the_initial_poll_collection_for_the_view(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ChatController.php'));

        $this->assertStringContainsString("'polls' => $polls", $source);
        $this->assertStringNotContainsString("'polls' => $group->polls()->with('options')->latest('id')->limit($initialPollLimit)->get()", $source);
    }

    public function test_group_info_panel_has_no_model_queries_in_its_bootstrap_block(): void
    {
        $source = file_get_contents(resource_path('views/groups/partials/group_info_panel.blade.php'));
        $bootstrap = strstr($source, '<div id="groupInfoPanel"', true) ?: $source;

        $this->assertStringNotContainsString('\\App\\Models\\Blog::', $bootstrap);
        $this->assertStringNotContainsString('\\App\\Models\\GroupUser::', $bootstrap);
        $this->assertStringNotContainsString('$group2->users()', $bootstrap);
    }
}
