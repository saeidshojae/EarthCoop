<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ElectionGroupSurfaceProjectionTest extends TestCase
{
    public function test_group_page_projects_systemic_cycle_and_snapshot_eligibility(): void
    {
        $runtime = file_get_contents(resource_path('views/groups/partials/page_chrome_runtime.blade.php'));

        $this->assertStringContainsString('ElectionEligibilitySnapshot::query()', $runtime);
        $this->assertStringContainsString("where('voter_eligible', true)", $runtime);
        $this->assertStringContainsString("data.chatPageAction = 'open-election'", str_replace('dataset.', 'data.', $runtime));
        $this->assertStringContainsString('انتخابات سیستمی · چرخه', $runtime);
        $this->assertStringContainsString('جزئیات و تاریخچه انتخابات', $runtime);
        $this->assertStringContainsString("document.getElementById('election')", $runtime);
    }

    public function test_group_panel_keeps_election_polls_separate_for_canonical_projection(): void
    {
        $panel = file_get_contents(resource_path('views/groups/partials/group_info_panel.blade.php'));
        $runtime = file_get_contents(resource_path('views/groups/partials/page_chrome_runtime.blade.php'));

        $this->assertStringContainsString('$electionPolls = $pollCollection->filter', $panel);
        $this->assertStringContainsString('(int)($poll->main_type ?? 1) === 0', $panel);
        $this->assertStringContainsString('electionTab.replaceChildren(card);', $runtime);
    }
}
