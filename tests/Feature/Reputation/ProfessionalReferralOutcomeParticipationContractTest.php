<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ProfessionalReferralOutcomeParticipationContractTest extends TestCase
{
    public function test_completed_professional_referral_is_the_only_reward_surface(): void
    {
        $source = file_get_contents(app_path('Modules/Governance/Services/ProfessionalReferralService.php'));

        $this->assertStringContainsString("if (\$referral->status !== 'in_review')", $source);
        $this->assertStringContainsString("'status' => 'completed'", $source);
        $this->assertStringContainsString("'completed_by' => \$actor->id", $source);
        $this->assertStringContainsString("'professional_referral_completed'", $source);
        $this->assertStringContainsString("'professional_referral_completed:referral:' . \$completed->id", $source);
    }

    public function test_professional_referral_outcome_is_non_convertible_by_default_and_once_per_context(): void
    {
        $this->assertSame(10, config('reputation.weights.professional_referral_completed'));
        $this->assertSame('participation', config('reputation.policy_defaults.professional_referral_completed.dimension'));
        $this->assertFalse(config('reputation.policy_defaults.professional_referral_completed.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.professional_referral_completed.repeat_policy'));
        $this->assertSame(50, config('reputation.daily_caps.professional_referral_completed'));
    }
}
