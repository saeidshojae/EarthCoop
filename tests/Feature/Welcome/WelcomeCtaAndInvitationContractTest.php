<?php

namespace Tests\Feature\Welcome;

use Tests\TestCase;

class WelcomeCtaAndInvitationContractTest extends TestCase
{
    public function test_welcome_primary_ctas_share_one_responsive_shape_contract(): void
    {
        $files = [
            'resources/views/partials/hero-section.blade.php',
            'resources/views/partials/projects-section.blade.php',
            'resources/views/partials/how-it-works-section.blade.php',
            'resources/views/partials/invite-section.blade.php',
            'resources/views/partials/cta-section.blade.php',
            'resources/views/partials/testimonials-section.blade.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringContainsString('welcome-cta', $source, $file . ' must use the shared Welcome CTA contract.');
        }

        $projects = file_get_contents(base_path('resources/views/partials/projects-section.blade.php'));
        $journey = file_get_contents(base_path('resources/views/partials/how-it-works-section.blade.php'));
        $invite = file_get_contents(base_path('resources/views/partials/invite-section.blade.php'));

        $this->assertStringNotContainsString('px-10 py-5', $projects);
        $this->assertStringNotContainsString('px-10 py-5', $journey);
        $this->assertStringNotContainsString('px-12 py-5', $invite);

        $css = file_get_contents(base_path('resources/css/app.css'));
        $this->assertStringContainsString('.welcome-cta {', $css);
        $this->assertStringContainsString('min-height:', $css);
        $this->assertStringContainsString('max-width:', $css);
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
