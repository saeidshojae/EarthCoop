<?php

namespace Tests\Feature\Welcome;

use Tests\TestCase;

class WelcomeCtaAndInvitationContractTest extends TestCase
{
    // Regression coverage for the mobile Welcome CTA and registration modal polish.
    public function test_welcome_primary_ctas_use_the_dedicated_pill_action_contract(): void
    {
        $projects = file_get_contents(base_path('resources/views/partials/projects-section.blade.php'));
        $journey = file_get_contents(base_path('resources/views/partials/how-it-works-section.blade.php'));
        $stories = file_get_contents(base_path('resources/views/partials/testimonials-section.blade.php'));
        $invite = file_get_contents(base_path('resources/views/partials/invite-section.blade.php'));
        $finalCta = file_get_contents(base_path('resources/views/partials/cta-section.blade.php'));

        foreach ([$projects, $journey, $stories] as $source) {
            $this->assertStringContainsString('welcome-action', $source);
            $this->assertStringContainsString('w-full max-w-sm', $source);
            $this->assertStringContainsString('px-9 py-4', $source);
            $this->assertStringContainsString('rounded-full', $source);
        }

        $this->assertStringContainsString('mb-4', $stories, 'The empty-story CTA needs breathing room above the card accent line.');

        $this->assertSame(2, substr_count($invite, 'welcome-action'));
        $this->assertStringContainsString('w-full sm:w-auto', $invite);
        $this->assertStringContainsString('px-9 py-4', $invite);
        $this->assertStringNotContainsString('welcome-wide-cta__button', $invite);

        $this->assertSame(2, substr_count($finalCta, 'welcome-action'));
        $this->assertStringContainsString('w-full sm:w-auto', $finalCta);
        $this->assertStringContainsString('px-9 py-4', $finalCta);
        $this->assertStringContainsString('rounded-full', $finalCta);
        $this->assertStringNotContainsString('welcome-wide-cta__button', $finalCta);
        $this->assertStringNotContainsString('px-12 py-5', $finalCta);

        $this->assertStringNotContainsString('px-10 py-5', $projects);
        $this->assertStringNotContainsString('px-10 py-5', $journey);
        $this->assertStringNotContainsString('px-12 py-5', $invite);
    }

    public function test_registration_modal_owns_the_viewport_and_scrolls_without_moving_the_page(): void
    {
        $welcome = file_get_contents(base_path('resources/views/welcome.blade.php'));

        $this->assertStringContainsString('id="registrationModal"', $welcome);
        $this->assertStringContainsString('z-[9999]', $welcome);
        $this->assertStringContainsString('overflow-y-auto', $welcome);
        $this->assertStringContainsString('items-start', $welcome);
        $this->assertStringContainsString('my-4 sm:my-8', $welcome);
        $this->assertStringContainsString("document.body.classList.add('overflow-hidden')", $welcome);
        $this->assertStringContainsString("document.body.classList.remove('overflow-hidden')", $welcome);
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
