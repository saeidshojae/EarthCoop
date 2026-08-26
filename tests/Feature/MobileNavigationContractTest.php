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

        $this->assertStringContainsString('x-data="{ openSection:', $drawer);
        $this->assertStringContainsString('@click="openSection = openSection ===', $drawer);
        $this->assertStringContainsString('navigation-section', $drawer);
    }

    public function test_public_sidebar_is_desktop_only_after_mobile_navigation_consolidation(): void
    {
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        $this->assertStringContainsString('hidden lg:block', $sidebar);
    }

    public function test_mobile_header_uses_the_navigation_drawer_component(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));

        $this->assertStringContainsString("@include('components.mobile-navigation-drawer'", $header);
        $this->assertStringContainsString('site-header-mobile-bar', $header);
    }

    public function test_mobile_profile_menu_is_scoped_to_account_actions(): void
    {
        $profile = file_get_contents(resource_path('views/components/user-dropdown-unified.blade.php'));

        $this->assertStringContainsString('mobile-account-only', $profile);
        $this->assertStringContainsString('desktop-expanded-nav', $profile);
    }
}
