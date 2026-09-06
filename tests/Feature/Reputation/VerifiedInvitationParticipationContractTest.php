<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class VerifiedInvitationParticipationContractTest extends TestCase
{
    public function test_verified_invitation_reward_uses_latest_lifecycle_and_policy(): void
    {
        $source = file_get_contents(app_path('Services/InvitationLifecycleService.php'));

        $this->assertStringContainsString("'invite_member'", $source);
        $this->assertStringContainsString("'registration_completion'", $source);
        $this->assertStringContainsString("'invite_member:referrer:' . \$referrer->id . ':member:' . \$invitee->id", $source);
        $this->assertStringContainsString('completed_at', $source);

        $this->assertSame(100, config('reputation.weights.invite_member'));
        $this->assertSame('participation', config('reputation.policy_defaults.invite_member.dimension'));
        $this->assertTrue(config('reputation.policy_defaults.invite_member.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.invite_member.repeat_policy'));
    }
}
