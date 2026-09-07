<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ElectionMobileRoleTabsContractTest extends TestCase
{
    public function test_mobile_ballot_has_role_tabs_and_centered_viewport_contract(): void
    {
        $js = file_get_contents(resource_path('js/group-chat/elections.js'));
        $css = file_get_contents(public_path('Css/group-chat-modals-responsive.css'));

        $this->assertStringContainsString('data-election-role-tab', $js);
        $this->assertStringContainsString('data-election-role-panel', $js);
        $this->assertStringContainsString('activateRoleTab', $js);
        $this->assertStringContainsString('.election-role-tabs', $css);
        $this->assertStringContainsString('width: calc(100vw - 12px)', $css);
        $this->assertStringContainsString('margin-inline: auto', $css);
    }

    public function test_poll_actions_are_pinned_to_modal_footer(): void
    {
        $css = file_get_contents(public_path('Css/group-chat-modals-responsive.css'));

        $this->assertStringContainsString('#pollOptionsBox .modal-shell__form', $css);
        $this->assertStringContainsString('#pollOptionsBox .modal-shell__actions', $css);
        $this->assertStringContainsString('margin-top: auto', $css);
        $this->assertStringContainsString('position: sticky', $css);
    }
}
