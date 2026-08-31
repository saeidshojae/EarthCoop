<?php

namespace Tests\Feature;

use Tests\TestCase;

class NajmBaharDashboardMobileUxContractTest extends TestCase
{
    public function test_mobile_runtime_is_scoped_to_dashboard_and_personal_wallet_only(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/najm-bahar-dashboard-mobile.js'));

        $this->assertStringContainsString("'/najm-bahar/dashboard'", $app);
        $this->assertStringContainsString("'/najm-bahar/wallet'", $app);
        $this->assertStringContainsString('najm-bahar-dashboard-mobile.js', $app);

        $this->assertStringContainsString("pathname === '/najm-bahar/dashboard'", $runtime);
        $this->assertStringContainsString("pathname === '/najm-bahar/wallet'", $runtime);
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
        $this->assertStringContainsString('تبدیل امتیاز به بهار', $runtime);
        $this->assertStringContainsString('حساب', $runtime);
        $this->assertStringContainsString('فعالیت', $runtime);
    }

    public function test_hero_coin_uses_canonical_float_and_spin_without_tilt_and_scales_depth_on_mobile(): void
    {
        $coin = file_get_contents(resource_path('views/components/bahar-coin.blade.php'));

        $this->assertStringContainsString('--bahar-coin-depth-scale: 1', $coin);
        $this->assertStringContainsString('--bahar-coin-depth-scale: 0.30', $coin);
        $this->assertStringContainsString('scaleZ(var(--bahar-coin-depth-scale))', $coin);
        $this->assertStringContainsString('transform: translate(-50%, -48%);', $coin);
        $this->assertStringContainsString('transform: none;', $coin);
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
