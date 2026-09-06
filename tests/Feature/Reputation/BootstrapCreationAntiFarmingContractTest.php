<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class BootstrapCreationAntiFarmingContractTest extends TestCase
{
    public function test_raw_content_creation_has_finite_bootstrap_reward_caps(): void
    {
        // Caps are points per rolling day, not raw event counts.
        // With current weights this rewards at most 5 posts/day and 10 comments/day.
        $this->assertSame(50, config('reputation.daily_caps.post_created'));
        $this->assertSame(20, config('reputation.daily_caps.comment_created'));
    }
}
