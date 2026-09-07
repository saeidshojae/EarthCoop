<?php

namespace Tests\Feature;

use Tests\TestCase;

class NajmBaharDashboardMobileUxContractTest extends TestCase
{
    public function test_mobile_navigation_runtime_is_loaded_by_sidebar_presence_not_path_allowlist(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/najm-bahar-dashboard-mobile.js'));

        $this->assertStringContainsString("document.querySelector('#najm-bahar-sidebar')", $app);
        $this->assertStringNotContainsString('const mobileUxPaths', $app);
        $this->assertStringContainsString('najm-bahar-dashboard-mobile.js', $app);
        $this->assertStringContainsString("pathname === '/najm-bahar/dashboard'", $runtime);
        $this->assertStringContainsString("pathname === '/najm-bahar/wallet'", $runtime);
        $this->assertStringContainsString("document.getElementById('najm-bahar-sidebar')", $runtime);
        $this->assertStringNotContainsString('if (!isDashboard && !isPersonalWallet) return;', $runtime);
        $this->assertStringContainsString('data-nb-mobile-nav-trigger', $runtime);
        $this->assertStringContainsString('data-nb-mobile-nav-sheet', $runtime);
        $this->assertStringContainsString('nb-mobile-menu-glow', $runtime);
        $this->assertStringContainsString('#f6c453', $runtime);
        $this->assertStringContainsString("matchMedia('(max-width: 1023px)')", $runtime);
    }

    public function test_dashboard_tabs_remain_dashboard_only(): void
    {
        $runtime = file_get_contents(resource_path('js/najm-bahar-dashboard-mobile.js'));
        $this->assertStringContainsString('data-nb-dashboard-tabs', $runtime);
        $this->assertStringContainsString('حساب من', $runtime);
        $this->assertStringContainsString('وضعیت سامانه', $runtime);
        $this->assertStringContainsString('if (!isDashboard)', $runtime);
    }

    public function test_personal_wallet_mobile_information_architecture_is_compact_and_tabbed(): void
    {
        $runtime = file_get_contents(resource_path('js/najm-bahar-dashboard-mobile.js'));
        $this->assertStringContainsString('data-nb-wallet-tabs', $runtime);
        $this->assertStringContainsString('data-nb-wallet-balance', $runtime);
        $this->assertStringContainsString('data-nb-wallet-points', $runtime);
        $this->assertStringContainsString('data-nb-wallet-transactions', $runtime);
        $this->assertStringContainsString('nb-wallet-transaction-list', $runtime);
        $this->assertStringContainsString('nb-wallet-hero-compact', $runtime);
        $this->assertStringContainsString('data-nb-wallet-hero-actions', $runtime);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $runtime);
        $this->assertStringContainsString('nb-wallet-points-compact', $runtime);
        $this->assertStringContainsString('تبدیل امتیاز به بهار', $runtime);
        $this->assertStringContainsString('حساب', $runtime);
        $this->assertStringContainsString('فعالیت', $runtime);
    }

    public function test_mobile_transaction_clone_is_never_visible_on_desktop(): void
    {
        $runtime = file_get_contents(resource_path('js/najm-bahar-dashboard-mobile.js'));
        $this->assertStringContainsString('.nb-wallet-transaction-list { display: none; }', $runtime);
        $this->assertStringContainsString('@media (max-width: 1023px)', $runtime);
        $this->assertStringContainsString('.nb-wallet-transaction-list { display: grid;', $runtime);
    }

    public function test_hero_coin_uses_direct_mobile_z_depth_instead_of_scene_scale(): void
    {
        $coin = file_get_contents(resource_path('views/components/bahar-coin.blade.php'));
        $this->assertStringContainsString('--bahar-coin-edge-step: 1px', $coin);
        $this->assertStringContainsString('--bahar-coin-face-depth: 9px', $coin);
        $this->assertStringContainsString('--bahar-coin-edge-step: 0.22px', $coin);
        $this->assertStringContainsString('--bahar-coin-face-depth: 2px', $coin);
        $this->assertStringContainsString('translateZ(calc(-8 * var(--bahar-coin-edge-step)))', $coin);
        $this->assertStringContainsString('translateZ(var(--bahar-coin-face-depth))', $coin);
        $this->assertStringNotContainsString('scaleZ(var(--bahar-coin-depth-scale))', $coin);
        $this->assertStringNotContainsString('rotate(-8deg)', $coin);
        $this->assertStringNotContainsString('rotate(-10deg)', $coin);
    }

    public function test_membership_modal_runtime_places_overlay_above_page_chrome(): void
    {
        $runtime = file_get_contents(resource_path('js/najm-bahar-membership-source.js'));
        $this->assertStringContainsString('membershipFeeModal', $runtime);
        $this->assertStringContainsString("modal.style.zIndex = '2147483000'", $runtime);
    }
}
