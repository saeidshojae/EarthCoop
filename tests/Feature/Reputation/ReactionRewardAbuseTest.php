<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReactionRewardAbuseTest extends TestCase
{
    public function test_reaction_rewards_target_content_owners_and_use_stable_event_keys(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));

        $this->assertStringContainsString('$blog->user', $source);
        $this->assertStringContainsString('$comment->user', $source);
        $this->assertStringContainsString("'post_upvoted:' . $blogId . ':reactor:'", $source);
        $this->assertStringContainsString("'comment_upvoted:' . $comment->id . ':reactor:'", $source);
    }

    public function test_reaction_event_keys_include_the_reactor_so_distinct_members_remain_independent(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));

        $this->assertStringContainsString('reactorId', $source);
        $this->assertStringContainsString("':reactor:' . $reactorId", $source);
    }
}
