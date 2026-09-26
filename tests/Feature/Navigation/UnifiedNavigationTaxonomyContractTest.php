<?php

namespace Tests\Feature\Navigation;

use Tests\TestCase;

class UnifiedNavigationTaxonomyContractTest extends TestCase
{
    public function test_authenticated_drawer_and_sidebar_share_the_approved_information_architecture(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        foreach (['شبکه و ارتباطات', 'حکمرانی و مشارکت', 'اقتصاد', 'سازمان و همکاری', 'حساب و پشتیبانی', 'کاوش و اسناد'] as $label) {
            $this->assertStringContainsString($label, $drawer);
            $this->assertStringContainsString($label, $sidebar);
        }

        foreach ([
            "route('home')",
            "route('groups.index')",
            "route('notifications.index')",
            "route('chat-requests.index')",
            "route('location-governance.me')",
            "route('history.index')",
            "route('community-stories.index')",
            "route('history.election')",
            "route('history.election-history')",
            "route('history.poll')",
            "route('najm-bahar.dashboard')",
            "route('stock.book')",
            "route('auction.index')",
            "route('secretariat.directory')",
            "route('my-invation-code')",
            "route('profile.edit')",
            "route('support.kb.index')",
        ] as $route) {
            $this->assertStringContainsString($route, $drawer);
            $this->assertStringContainsString($route, $sidebar);
        }
    }

    public function test_legacy_stock_wallet_navigation_is_removed_and_value_share_labels_are_canonical(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        $this->assertStringNotContainsString("route('wallet.index')", $drawer);
        $this->assertStringNotContainsString("route('holding.index')", $drawer);

        foreach (['دفتر سهام ارزش', 'حراج‌های سهم ارزش'] as $label) {
            $this->assertStringContainsString($label, $drawer);
            $this->assertStringContainsString($label, $sidebar);
        }
    }

    public function test_home_hoda_introduction_uses_the_existing_canonical_profile_instead_of_a_duplicate_about_page(): void
    {
        $homePolish = file_get_contents(resource_path('views/components/home-shell-polish.blade.php'));
        $provider = file_get_contents(app_path('Providers/RouteServiceProvider.php'));

        $this->assertStringContainsString("route('najm-hoda.profile')", $homePolish);
        $this->assertStringNotContainsString("route('najm-hoda.about')", $homePolish);
        $this->assertStringNotContainsString("routes/najm-hoda-about.php", $provider);
        $this->assertFileDoesNotExist(base_path('routes/najm-hoda-about.php'));
        $this->assertFileDoesNotExist(resource_path('views/najm-hoda/about.blade.php'));
    }
}
