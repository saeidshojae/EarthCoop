<?php

namespace Tests\Feature\Welcome;

use Tests\TestCase;

class WelcomeCtaAndInvitationContractTest extends TestCase
{
    public function test_welcome_primary_ctas_use_the_dedicated_pill_action_contract(): void
    {
        $projects = file_get_contents(base_path('resources/views/partials/projects-section.blade.php'));
        $journey = file_get_contents(base_path('resources/views/partials/how-it-works-section.blade.php'));
        $invite = file_get_contents(base_path('resources/views/partials/invite-section.blade.php'));

        $this->assertStringContainsString('welcome-action', $projects);
        $this->assertStringContainsString('welcome-action', $journey);
        $this->assertSame(2, substr_count($invite, 'welcome-action'));

        $this->assertStringNotContainsString('px-10 py-5', $projects);
        $this->assertStringNotContainsString('px-10 py-5', $journey);
        $this->assertStringNotContainsString('px-12 py-5', $invite);

        $css = file_get_contents(base_path('resources/css/app.css'));
        $this->assertStringContainsString('.welcome-action {', $css);
        $this->assertStringContainsString('min-height: 3.5rem;', $css);
        $this->assertStringContainsString('padding: 1rem 2.25rem;', $css);
        $this->assertStringContainsString('border-radius: 9999px;', $css);
        $this->assertStringContainsString('border-radius: 9999px !important;', $css);
    }

    public function test_invitation_copy_uses_current_participation_credit_model_not_old_bahar_cash_claims(): void
    {
        $fa = file_get_contents(base_path('resources/lang/fa/langWelcome.php'));
        $en = file_get_contents(base_path('resources/lang/en/langWelcome.php'));
        $ar = file_get_contents(base_path('resources/lang/ar/langWelcome.php'));
        $inviteView = file_get_contents(base_path('resources/views/partials/invite-section.blade.php'));

        $this->assertStringContainsString('اعتبار مشارکت', $fa);
        $this->assertStringNotContainsString('سکه‌های بهار کسب کنید', $fa);
        $this->assertStringNotContainsString('earn Bahar coins', $en);
        $this->assertStringNotContainsString('voting weight', $en);
        $this->assertStringContainsString('inviteRewardRule', $inviteView);
        $this->assertStringContainsString("route('participation.credit-regulation')", $inviteView);
    }

    public function test_projects_cta_copy_matches_its_registration_action(): void
    {
        $fa = file_get_contents(base_path('resources/lang/fa/langWelcome.php'));
        $projects = file_get_contents(base_path('resources/views/partials/projects-section.blade.php'));

        $this->assertStringContainsString('برای مشارکت بپیوندید', $fa);
        $this->assertStringContainsString('openModal()', $projects);
    }
}
