<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationAdminSemanticLabelsContractTest extends TestCase
{
    public function test_admin_catalogue_uses_current_persian_semantics_for_participation_and_governance_rules(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));

        $this->assertStringContainsString("'invite_member' => 'دعوت موفق عضو جدید'", $controller);
        $this->assertStringContainsString("'membership_fee_paid' => 'پرداخت حق عضویت سالانه'", $controller);
        $this->assertStringContainsString("'post_liked' => 'پسندیدن پست دیگران'", $controller);
        $this->assertStringContainsString("'post_upvoted' => 'دریافت پسند برای پست'", $controller);
        $this->assertStringContainsString("'comment_liked' => 'پسندیدن دیدگاه دیگران'", $controller);
        $this->assertStringContainsString("'comment_upvoted' => 'دریافت پسند برای دیدگاه'", $controller);
        $this->assertStringContainsString("'professional_referral_completed' => 'تکمیل ارجاع تخصصی تأییدشده'", $controller);
        $this->assertStringContainsString("'election_participated' => 'منسوخ — مشارکت عمومی انتخابات قدیمی'", $controller);
        $this->assertStringContainsString("'election_candidate' => 'منسوخ — نامزدی در مدل قدیمی انتخابات'", $controller);
        $this->assertStringContainsString("'governance' => ['label' => 'حاکمیت و انتخابات'", $controller);
        $this->assertStringContainsString("'membership' => ['label' => 'عضویت و دعوت'", $controller);
        $this->assertStringContainsString("'archived' => ['label' => 'آرشیو / منسوخ'", $controller);
    }
}
