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
}
