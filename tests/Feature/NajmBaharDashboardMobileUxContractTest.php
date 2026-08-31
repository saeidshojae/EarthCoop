<?php

namespace Tests\Feature;

use Tests\TestCase;

class NajmBaharDashboardMobileUxContractTest extends TestCase
{
    public function test_dashboard_mobile_runtime_contract_is_present_and_scoped(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/najm-bahar-dashboard-mobile.js'));

        $this->assertStringContainsString("window.location.pathname === '/najm-bahar/dashboard'", $app);
        $this->assertStringContainsString('najm-bahar-dashboard-mobile.js', $app);

        $this->assertStringContainsString('data-nb-mobile-nav-trigger', $runtime);
        $this->assertStringContainsString('data-nb-mobile-nav-sheet', $runtime);
        $this->assertStringContainsString('data-nb-dashboard-tabs', $runtime);
        $this->assertStringContainsString('حساب من', $runtime);
        $this->assertStringContainsString('وضعیت سامانه', $runtime);
        $this->assertStringContainsString("matchMedia('(max-width: 1023px)')", $runtime);
    }

    public function test_membership_modal_runtime_places_overlay_above_page_chrome(): void
    {
        $runtime = file_get_contents(resource_path('js/najm-bahar-membership-source.js'));

        $this->assertStringContainsString('membershipFeeModal', $runtime);
        $this->assertStringContainsString("modal.style.zIndex = '2147483000'", $runtime);
    }
}
