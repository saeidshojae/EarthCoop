<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class VerifiedInvitationParticipationContractTest extends TestCase
{
    public function test_verified_referral_reward_is_bound_to_consumed_invitation_and_stable_referrer_member_identity(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/NajmBaharController.php'));

        $this->assertStringContainsString("InvitationCode::where('used_by', \$user->id)->first()", $source);
        $this->assertStringContainsString("if (! \$invitationCheck || (int) \$invitationCheck->user_id === 171)", $source);
        $this->assertStringContainsString("'invite_member'", $source);
        $this->assertStringContainsString("'invite_member:referrer:' . \$referrer->id . ':member:' . \$user->id", $source);
    }

    public function test_invite_member_bootstrap_rule_is_economic_once_per_verified_member_context(): void
    {
        $this->assertSame(10, config('reputation.weights.invite_member'));
        $this->assertSame('participation', config('reputation.policy_defaults.invite_member.dimension'));
        $this->assertTrue(config('reputation.policy_defaults.invite_member.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.invite_member.repeat_policy'));
    }
}
