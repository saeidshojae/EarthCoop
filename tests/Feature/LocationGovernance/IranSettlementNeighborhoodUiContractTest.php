<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

final class IranSettlementNeighborhoodUiContractTest extends TestCase
{
    public function test_registration_bridge_is_hidden_until_rural_parent_and_keeps_pending_neighborhood_separate(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));
        $selector = file_get_contents(resource_path('js/location-selector-core.js'));
        $bridge = file_get_contents(resource_path('js/registration-settlement-bridge.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-reference-settlement-picker hidden', $view);
        $this->assertStringContainsString('data-reference-settlement-neighborhood', $view);
        $this->assertStringContainsString('data-reference-settlement-picker', $profile);
        $this->assertStringContainsString('name="reference_settlement_external_id"', $profile);
        $this->assertStringContainsString('data-reference-settlement-current-external-id', $profile);
        $this->assertStringContainsString('earthcoop-location-selection-changed', $selector);
        $this->assertStringContainsString("if (isProjectScope) return;", $selector);
        $this->assertStringContainsString("['registration', 'profile'].includes(selectorContext)", $bridge);
        $this->assertStringContainsString('persistedSettlementExternalId', $bridge);
        $this->assertStringContainsString('persistedNeighborhoodProposalId', $bridge);
        $this->assertStringContainsString("item?.type_key !== 'rural_district'", $bridge);
        $this->assertStringContainsString('shell.hidden = false', $bridge);
        $this->assertStringContainsString('option.dataset.referenceSettlementOption', $bridge);
        $this->assertStringContainsString("appendPathSegment('آبادی ' + item.name_fa", $bridge);
        $this->assertStringContainsString("segment.dataset.referenceSettlementPath", $bridge);
        $this->assertStringContainsString("segment.dataset.referenceNeighborhoodPath", $bridge);
        $this->assertStringNotContainsString("path.textContent =", $bridge);
        $this->assertStringContainsString("presentSettlement(item, retries - 1)", $bridge);
        $this->assertStringContainsString('parent_reference_settlement_id', $bridge);
        $this->assertStringContainsString("settlementNeighborhoodProposalId = String(id)", $bridge);
        $this->assertStringContainsString("proposalInput.value === settlementNeighborhoodProposalId", $bridge);
        $this->assertStringNotContainsString("const clearNeighborhood = () => {\n        neighborhoodHost.innerHTML = '';\n        proposalInput.value = '';", $bridge);
        $this->assertStringContainsString('registration-settlement-bridge.js', $app);
    }
}
