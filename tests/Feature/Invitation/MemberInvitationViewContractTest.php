<?php

namespace Tests\Feature\Invitation;

use Tests\TestCase;

class MemberInvitationViewContractTest extends TestCase
{
    // Locks the responsive invitation UX and the human-readable share/deep-link contract.
    public function test_member_invitation_page_has_mobile_first_cards_and_a_standard_primary_action(): void
    {
        $view = file_get_contents(base_path('resources/views/profile/member-invitations.blade.php'));

        $this->assertStringContainsString('invite-page-shell', $view);
        $this->assertStringContainsString('invite-primary-action', $view);
        $this->assertStringContainsString('rounded-full', $view);
        $this->assertStringContainsString('mobile-invite-cards', $view);
        $this->assertStringContainsString('desktop-invite-table', $view);
        $this->assertStringContainsString('md:hidden', $view);
        $this->assertStringContainsString('hidden md:block', $view);
    }

    public function test_share_action_builds_a_human_invitation_and_copies_the_whole_message_as_fallback(): void
    {
        $view = file_get_contents(base_path('resources/views/profile/member-invitations.blade.php'));

        $this->assertStringContainsString('function buildInviteMessage(code)', $view);
        $this->assertStringContainsString('از اعضای نخستین', $view);
        $this->assertStringContainsString("'?invite='", $view);
        $this->assertStringContainsString('navigator.share', $view);
        $this->assertStringContainsString('copyToClipboard(message)', $view);
        $this->assertStringNotContainsString('copyToClipboard(url)', $view);
    }

    public function test_desktop_share_uses_earthcoop_menu_before_native_system_share(): void
    {
        $view = file_get_contents(base_path('resources/views/profile/member-invitations.blade.php'));

        $this->assertStringContainsString('id="inviteShareMenu"', $view);
        $this->assertStringContainsString('function isMobileShareContext()', $view);
        $this->assertStringContainsString('function openInviteShareMenu(code)', $view);
        $this->assertStringContainsString('function shareInviteViaSystem()', $view);
        $this->assertStringContainsString('اشتراک از طریق سیستم', $view);
        $this->assertStringContainsString('واتساپ', $view);
        $this->assertStringContainsString('تلگرام', $view);
        $this->assertStringContainsString('کپی لینک دعوت', $view);
        $this->assertStringContainsString('if (isMobileShareContext() && navigator.share)', $view);
    }

    public function test_invitation_page_does_not_repeat_old_fixed_bahar_cash_reward_claims(): void
    {
        $view = file_get_contents(base_path('resources/views/profile/member-invitations.blade.php'));

        $this->assertStringNotContainsString('۱۰ بهار', $view);
        $this->assertStringNotContainsString('۱ گرم طلای ۲۴ عیار', $view);
        $this->assertStringContainsString("route('participation.credit-regulation')", $view);
    }

    public function test_welcome_can_prefill_and_open_registration_from_an_invitation_link(): void
    {
        $welcome = file_get_contents(base_path('resources/views/welcome.blade.php'));

        $this->assertStringContainsString("searchParams.get('invite')", $welcome);
        $this->assertStringContainsString("querySelector('[name=\"invite_code\"]')", $welcome);
        $this->assertStringContainsString('openModal()', $welcome);
    }
}
