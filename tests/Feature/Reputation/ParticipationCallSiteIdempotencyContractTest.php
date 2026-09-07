<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationCallSiteIdempotencyContractTest extends TestCase
{
    public function test_post_and_comment_creation_awards_use_stable_business_event_keys(): void
    {
        $blog = file_get_contents(app_path('Http/Controllers/Group/BlogController.php'));
        $comment = file_get_contents(app_path('Http/Controllers/Group/CommentController.php'));

        $this->assertStringContainsString("'post_created:' . \$blog->id . ':author:' . auth()->id()", $blog);
        $this->assertStringContainsString("'comment_created:' . \$comment->id . ':author:' . auth()->id()", $comment);
    }

    public function test_verified_referral_award_uses_member_identity_as_stable_business_event_key(): void
    {
        $source = file_get_contents(app_path('Services/InvitationLifecycleService.php'));

        $this->assertStringContainsString("'invite_member:referrer:' . \$referrer->id . ':member:' . \$invitee->id", $source);
    }

    public function test_membership_fee_award_uses_member_and_payment_year_as_stable_business_event_key(): void
    {
        $source = file_get_contents(app_path('Services/ReputationService.php'));

        $this->assertStringContainsString("\$actionKey === 'membership_fee_paid'", $source);
        $this->assertStringContainsString("'membership_fee_paid:user:' . \$user->id . ':year:' . \$referenceId", $source);
    }
}
