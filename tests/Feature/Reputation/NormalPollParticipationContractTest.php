<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class NormalPollParticipationContractTest extends TestCase
{
    public function test_only_normal_group_polls_award_creation_and_participation(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/PollController.php'));

        $this->assertStringContainsString("(int) \$poll->main_type !== 1", $source);
        $this->assertStringContainsString("'poll_created:poll:' . \$poll->id . ':creator:' . \$poll->created_by", $source);
        $this->assertStringContainsString("'poll_participated:poll:' . \$poll->id . ':user:' . \$user->id", $source);
    }

    public function test_poll_bootstrap_policy_is_convertible_bounded_and_once_per_context(): void
    {
        $this->assertSame('participation', config('reputation.policy_defaults.poll_created.dimension'));
        $this->assertTrue(config('reputation.policy_defaults.poll_created.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.poll_created.repeat_policy'));
        $this->assertSame(25, config('reputation.daily_caps.poll_created'));

        $this->assertSame('participation', config('reputation.policy_defaults.poll_participated.dimension'));
        $this->assertTrue(config('reputation.policy_defaults.poll_participated.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.poll_participated.repeat_policy'));
        $this->assertSame(100, config('reputation.daily_caps.poll_participated'));
    }
}
