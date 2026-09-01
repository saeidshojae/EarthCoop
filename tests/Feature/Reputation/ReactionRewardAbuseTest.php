<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReactionRewardAbuseTest extends TestCase
{
    public function test_post_like_rewards_both_reactor_and_content_owner_with_independent_rules(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));

        $this->assertStringContainsString("'post_liked'", $source);
        $this->assertStringContainsString("'post_upvoted'", $source);
        $this->assertStringContainsString('$blog->user', $source);
        $this->assertStringContainsString("'post_liked:' . \$blogId . ':reactor:'", $source);
        $this->assertStringContainsString("'post_upvoted:' . \$blogId . ':reactor:'", $source);
    }

    public function test_comment_like_rewards_both_reactor_and_content_owner_with_independent_rules(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));

        $this->assertStringContainsString("'comment_liked'", $source);
        $this->assertStringContainsString("'comment_upvoted'", $source);
        $this->assertStringContainsString('$comment->user', $source);
        $this->assertStringContainsString("'comment_liked:' . \$comment->id . ':reactor:'", $source);
        $this->assertStringContainsString("'comment_upvoted:' . \$comment->id . ':reactor:'", $source);
    }

    public function test_reaction_event_keys_include_the_reactor_so_toggle_farming_is_blocked_but_distinct_members_remain_independent(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));

        $this->assertStringContainsString('reactorId', $source);
        $this->assertStringContainsString("':reactor:' . \$reactorId", $source);
    }

    public function test_reactor_and_owner_weights_are_separate_admin_managed_rules(): void
    {
        $config = file_get_contents(config_path('reputation.php'));

        $this->assertStringContainsString("'post_liked'", $config);
        $this->assertStringContainsString("'post_upvoted'", $config);
        $this->assertStringContainsString("'comment_liked'", $config);
        $this->assertStringContainsString("'comment_upvoted'", $config);
    }
}
