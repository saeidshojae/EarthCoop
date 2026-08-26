<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileNavigationContractTest extends TestCase
{
    public function test_mobile_drawer_keeps_core_navigation_grouped_and_collapsible(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        foreach ([
            "route('home')",
            "route('notifications.index')",
            "route('chat-requests.index')",
            "route('groups.index')",
            "route('history.index')",
            "route('history.election')",
            "route('history.poll')",
            "route('najm-bahar.dashboard')",
            "route('my-invation-code')",
            "route('secretariat.directory')",
            "route('support.kb.index')",
            "route('user.tickets.index')",
            "route('user.support-chat.index')",
        ] as $routeContract) {
            $this->assertStringContainsString($routeContract, $drawer);
        }

        foreach (['primary', 'participation', 'economy', 'organization', 'support', 'explore'] as $section) {
            $this->assertStringContainsString("openSection === '{$section}'", $drawer);
        }

        $this->assertStringContainsString('x-data="{ openSection:', $drawer);
        $this->assertStringContainsString('@click="openSection = openSection ===', $drawer);
        $this->assertStringContainsString('navigation-section', $drawer);
    }

    public function test_public_sidebar_is_hidden_on_mobile_after_navigation_consolidation(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));

        $this->assertStringContainsString('.unified-public-sidebar { display: none !important; }', $header);
        $this->assertStringContainsString('@media (max-width: 1023px)', $header);
    }

    public function test_mobile_header_uses_the_navigation_drawer_and_account_components(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));

        $this->assertStringContainsString("@include('components.mobile-navigation-drawer'", $header);
        $this->assertStringContainsString("@include('components.mobile-account-menu')", $header);
        $this->assertStringContainsString('site-header-mobile-bar', $header);
        $this->assertStringContainsString('height: 60px !important', $header);
    }

    public function test_mobile_account_menu_is_scoped_to_account_actions_and_viewport_anchored(): void
    {
        $account = file_get_contents(resource_path('views/components/mobile-account-menu.blade.php'));

        $this->assertStringContainsString("route('profile.show')", $account);
        $this->assertStringContainsString("route('profile.edit')", $account);
        $this->assertStringContainsString("route('logout')", $account);
        $this->assertStringContainsString('mobile-account-dropdown', $account);
        $this->assertStringNotContainsString("route('auction.index')", $account);
        $this->assertStringNotContainsString("route('support.kb.index')", $account);
        $this->assertStringNotContainsString("route('secretariat.directory')", $account);
    }

    public function test_back_navigation_uses_browser_history_instead_of_server_previous_url(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));

        $this->assertStringContainsString('earthcoopNavigateBack', $header);
        $this->assertStringContainsString('window.history.back()', $header);
        $this->assertStringContainsString("route('home')", $header);
        $this->assertStringContainsString('site-header-mobile-back', $header);
        $this->assertStringNotContainsString('url()->previous()', $header);
        $this->assertStringNotContainsString('$backUrl', $header);
    }
}
