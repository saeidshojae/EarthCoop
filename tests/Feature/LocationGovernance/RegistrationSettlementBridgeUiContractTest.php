<?php

namespace Tests\Feature\LocationGovernance;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationSettlementBridgeUiContractTest extends TestCase
{
    #[Test]
    public function canonical_registration_view_exposes_settlement_bridge_only_behind_independent_gate(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));
        $bridge = file_get_contents(resource_path('js/registration-settlement-bridge.js'));
        $selector = file_get_contents(resource_path('js/location-selector-core.js'));

        $this->assertIsString($view);
        $this->assertStringContainsString("config('iran_settlement_catalog.registration_bridge_enabled'", $view);
        $this->assertStringContainsString('name="reference_settlement_external_id"', $view);
        $this->assertStringContainsString('data-settlement-registration-bridge', $view);
        $this->assertStringContainsString('data-settlement-search-input', $view);
        $this->assertStringContainsString('data-settlement-search-results', $view);
        $this->assertStringContainsString('registration-settlement-bridge.js', $app);
        $this->assertStringContainsString('earthcoop-location-selection-changed', $selector);
        $this->assertStringContainsString('parent_location_id', $bridge);
        $this->assertStringContainsString('rural_district', $bridge);
        $this->assertStringContainsString('locationInput.value =', $bridge);
        $this->assertStringContainsString('proposalInput.value =', $bridge);
        $this->assertStringContainsString('hidden.value = item.external_id', $bridge);
    }
}
