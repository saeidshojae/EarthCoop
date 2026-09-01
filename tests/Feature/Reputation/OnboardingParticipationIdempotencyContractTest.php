<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class OnboardingParticipationIdempotencyContractTest extends TestCase
{
    public function test_email_verification_award_uses_a_stable_user_business_event_key(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Auth/EmailVerificationController.php'));

        $this->assertStringContainsString("'email_verified:user:' . \$user->id", $source);
    }

    public function test_profile_completion_award_uses_a_stable_user_business_event_key(): void
    {
        $source = file_get_contents(app_path('Services/ProfileCompletionService.php'));

        $this->assertStringContainsString("'profile_completed:user:' . \$user->id", $source);
    }
}
