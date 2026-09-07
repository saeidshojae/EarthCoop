<?php

namespace Tests\Feature;

use Tests\TestCase;

class GuestHeaderSelfContainedContractTest extends TestCase
{
    public function test_guest_header_critical_polish_does_not_depend_on_vite(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        $this->assertStringContainsString('guest-navigation-cta--login', $drawer);
        $this->assertStringContainsString('guest-navigation-cta--join', $drawer);
        $this->assertStringContainsString('guest-navigation-cta--invite', $drawer);
        $this->assertStringContainsString('background: #3b82f6 !important;', $drawer);
        $this->assertStringContainsString('background: #10b981 !important;', $drawer);
        $this->assertStringContainsString('background: #f59e0b !important;', $drawer);

        $this->assertStringContainsString('data-auth-state="guest"', $drawer);
        $this->assertStringContainsString('height: 60px !important;', $drawer);
        $this->assertStringContainsString('earthcoop-header-logo-float-soft', $drawer);
    }

    public function test_back_navigation_has_a_non_vite_same_origin_fallback_runtime(): void
    {
        $runtime = file_get_contents(public_path('js/dark-mode.js'));

        $this->assertStringContainsString('window.earthcoopNavigateBack', $runtime);
        $this->assertStringContainsString('document.referrer', $runtime);
        $this->assertStringContainsString('window.history.back();', $runtime);
        $this->assertStringContainsString('data-earthcoop-history-back', $runtime);
        $this->assertStringNotContainsString('sessionStorage', $runtime);
        $this->assertStringNotContainsString('earthcoop.navigation.stack', $runtime);
    }
}
