<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class MyParticipationMobileSurfaceContractTest extends TestCase
{
    public function test_six_legacy_participation_tabs_have_mobile_native_card_adapters(): void
    {
        $runtime = file_get_contents(resource_path('js/my-participation-mobile.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('my-participation-mobile.js', $app);
        $this->assertStringContainsString('contributionMobileSchemas', $runtime);
        $this->assertStringContainsString("'tab-posts'", $runtime);
        $this->assertStringContainsString("'tab-comments'", $runtime);
        $this->assertStringContainsString("'tab-replies'", $runtime);
        $this->assertStringContainsString("'tab-reactions'", $runtime);
        $this->assertStringContainsString("'tab-polls'", $runtime);
        $this->assertStringContainsString("'tab-votes'", $runtime);
        $this->assertStringContainsString('contribution-mobile-cards', $runtime);
        $this->assertStringContainsString('contribution-mobile-card', $runtime);
        $this->assertStringContainsString("setAttribute('role', 'list')", $runtime);
        $this->assertStringContainsString("setAttribute('role', 'listitem')", $runtime);
    }

    public function test_mobile_breakpoint_hides_legacy_tables_and_keeps_navigation_touch_friendly_without_horizontal_scroll(): void
    {
        $css = file_get_contents(resource_path('css/my-participation-mobile.css'));

        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString('.mobile-adapted-participation .data-table-wrapper', $css);
        $this->assertStringContainsString('display: none !important;', $css);
        $this->assertStringContainsString('.contribution-mobile-cards', $css);
        $this->assertStringContainsString('.contributions-section .tabs-navigation', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('min-height: 44px;', $css);
        $this->assertStringContainsString('min-width: 0;', $css);
        $this->assertStringContainsString('overflow: visible;', $css);
    }
}
