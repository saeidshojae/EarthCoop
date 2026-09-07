<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ElectionBallotInteractionContractTest extends TestCase
{
    public function test_ballot_keeps_role_selections_independent_and_preserves_existing_conflict_choice(): void
    {
        $source = file_get_contents(resource_path('js/group-chat/elections.js'));

        $this->assertStringContainsString("selectedFor(form, role).length", $source);
        $this->assertStringContainsString("input.checked = false;", $source);
        $this->assertStringContainsString('برای تغییر نقش ابتدا انتخاب قبلی او را بردارید', $source);
        $this->assertStringNotContainsString('if (other?.checked) other.checked = false;', $source);
    }

    public function test_native_privacy_controls_are_not_cancelled_by_modal_container_action(): void
    {
        $source = file_get_contents(resource_path('js/group-chat/elections.js'));

        $this->assertStringContainsString("actions.register('election-content', ({ event }) => (event.stopPropagation(), false));", $source);
        $this->assertStringContainsString("visibility.disabled = false;", $source);
        $this->assertStringContainsString("select.style.pointerEvents = active ? 'auto' : 'none';", $source);
    }

    public function test_backend_does_not_forbid_self_vote(): void
    {
        $source = file_get_contents(app_path('Services/Elections/ElectionBallotService.php'));

        $this->assertStringNotContainsString('$candidateUserId === $voterId', $source);
        $this->assertStringNotContainsString('$candidateUserId == $voterId', $source);
        $this->assertStringContainsString('where(\'selectable_eligible\', true)', $source);
    }
}
