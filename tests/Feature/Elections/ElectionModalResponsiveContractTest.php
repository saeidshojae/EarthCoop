<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ElectionModalResponsiveContractTest extends TestCase
{
    public function test_chat_modal_styles_own_mobile_viewport_and_internal_scroll(): void
    {
        $css = file_get_contents(public_path('Css/group-chat-modals-responsive.css'));
        $post = file_get_contents(resource_path('views/groups/modals/post_form.blade.php'));

        $this->assertStringContainsString('height: 100dvh !important;', $css);
        $this->assertStringContainsString('overscroll-behavior: contain !important;', $css);
        $this->assertStringContainsString('.modal-shell__form', $css);
        $this->assertStringContainsString('overflow-y: auto !important;', $css);
        $this->assertStringContainsString('.election-modal-body', $css);
        $this->assertStringContainsString('body:has(.modal-shell[style*="display: flex"])', $css);
        $this->assertStringContainsString('group-chat-modals-responsive.css', $post);
    }

    public function test_systemic_ballot_renders_member_avatar_with_initial_fallback(): void
    {
        $view = file_get_contents(resource_path('views/groups/modals/election_modal.blade.php'));

        $this->assertStringContainsString('election-member-avatar', $view);
        $this->assertStringContainsString("asset('storage/'.ltrim", $view);
        $this->assertStringContainsString('$memberInitials', $view);
        $this->assertStringNotContainsString('data-group-chat-action="election-content"', $view);
    }
}
