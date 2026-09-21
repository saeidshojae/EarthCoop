<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class CurrentElectionCenterResponsiveContractTest extends TestCase
{
    public function test_current_center_is_card_first_and_cannot_force_mobile_overflow(): void
    {
        $source = file_get_contents(resource_path('views/history/election.blade.php'));

        $this->assertIsString($source);
        $this->assertMatchesRegularExpression('/\.election-center-page[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.election-center-grid[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.election-card[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.election-card__actions[^{]*\{[^}]*flex-wrap:\s*wrap/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.election-card__actions[^{]*\{[^}]*flex-direction:\s*column/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.election-card__actions\s+a[^{]*\{[^}]*width:\s*100%/s', $source);
        $this->assertStringNotContainsString('min-width: 700px', $source);
        $this->assertStringNotContainsString('min-width: 880px', $source);
    }

    public function test_election_portal_has_mobile_safe_touch_and_overflow_contract(): void
    {
        $source = file_get_contents(resource_path('views/elections/portal.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('election-user-portal', $source);
        $this->assertMatchesRegularExpression('/\.election-user-portal[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.election-user-portal \.btn,[\s\S]*?min-height:\s*44px/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*767\.98px\)[\s\S]*?election-user-portal__header-actions[\s\S]*?width:\s*100%/s', $source);
        $this->assertStringContainsString('padding-bottom: 6rem', $source);
    }

}
