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

    public function test_back_navigation_has_component_level_same_tab_fallback(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        $this->assertStringContainsString("'earthcoop.navigation.stack'", $drawer);
        $this->assertStringContainsString('$mobileBackFallback = $isAuth ? route(\'home\') : route(\'welcome\');', $drawer);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $drawer);
        $this->assertStringContainsString('window.location.assign(target);', $drawer);
    }
}
