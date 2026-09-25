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
        $this->assertStringContainsString('data-reference-settlement-picker', $profile);
        $this->assertStringContainsString('name="reference_settlement_external_id"', $profile);
        $this->assertStringContainsString('data-reference-settlement-current-external-id', $profile);
        $this->assertStringContainsString('earthcoop-location-selection-changed', $selector);
        $this->assertStringContainsString("if (isProjectScope) return;", $selector);
        $this->assertStringContainsString("['registration', 'profile', 'admin-user-residence'].includes(selectorContext)", $bridge);
        $this->assertStringContainsString('persistedSettlementExternalId', $bridge);
        $this->assertStringContainsString('persistedNeighborhoodProposalId', $bridge);
        $this->assertStringContainsString("parentLocationId = String(event.detail.locationId);\n        selector.dataset.referenceBranchActive = '1';\n        shell.hidden = true;", $bridge);
        $this->assertStringNotContainsString("if (!await requestSettlements('', false)) return;", $bridge);
        $this->assertStringContainsString("item?.type_key !== 'rural_district'", $bridge);
        $this->assertStringContainsString('earthcoop-location-exception-open', $bridge);
        $this->assertStringContainsString('mount.appendChild(shell)', $bridge);
        $this->assertStringContainsString('shell.hidden = false', $bridge);
        $this->assertStringContainsString('dataset.locationExceptionPanel', $selector);
        $this->assertStringContainsString('روستا یا آبادی من در فهرست نیست', $selector);
        $this->assertStringNotContainsString('single_urban_region', $selector);
        $this->assertStringNotContainsString('single_neighborhood', $selector);
        $this->assertStringContainsString('option.dataset.referenceSettlementOption', $bridge);
        $this->assertStringNotContainsString("path.textContent =", $bridge);
        $this->assertStringContainsString('earthcoop-location-reference-selected', $bridge);
        $this->assertStringContainsString('earthcoop-location-reference-selected', $selector);
        $this->assertStringContainsString('parentReferenceSettlementExternalId', $selector);
        $this->assertStringContainsString('/location/reference-settlements/${encodeURIComponent(parentReferenceSettlementExternalId)}/children', $selector);
        $this->assertStringContainsString('Number(settlement.id)', $selector);
        $this->assertStringContainsString("String(settlement.external_id || '')", $selector);
        $this->assertStringContainsString('settlementSelect.value = settlementItem.identity', $selector);
        $this->assertStringContainsString("settlementOption.dataset.referenceSettlementOption = ''", $selector);
        $this->assertStringContainsString("settlementOption.dataset.typeKey = 'settlement'", $selector);
        $this->assertStringContainsString('earthcoop-location-path-changed', $selector);
        $this->assertStringContainsString('proposalPath', $selector);
        $this->assertStringContainsString("type_key: 'settlement'", $selector);
        $this->assertStringContainsString("presentSettlement(item, retries - 1)", $bridge);
        $this->assertStringContainsString('loadSettlementContinuation', $bridge);
        $this->assertStringContainsString('closeDisclosure();', $bridge);
        $this->assertStringContainsString('dispatchReferenceSelection(null, persistedProposalPath, payload)', $bridge);
        $this->assertStringContainsString("proposalInput.value === settlementNeighborhoodProposalId", $bridge);
        $this->assertStringNotContainsString("const clearNeighborhood = () => {\n        neighborhoodHost.innerHTML = '';\n        proposalInput.value = '';", $bridge);
        $this->assertStringContainsString('registration-settlement-bridge.js', $app);
    }

    public function test_registration_profile_and_admin_are_locked_to_one_shared_picker_contract(): void
    {
        $registration = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));
        $admin = file_get_contents(resource_path('views/admin/user/partials/canonical-residence.blade.php'));
        $core = file_get_contents(resource_path('js/location-selector-core.js'));
        $bridge = file_get_contents(resource_path('js/registration-settlement-bridge.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $contexts = [
            'registration' => $registration,
            'profile' => $profile,
            'admin-user-residence' => $admin,
        ];

        foreach ($contexts as $context => $view) {
            $this->assertStringContainsString('data-location-selector', $view, $context);
            $this->assertStringContainsString('data-location-selector-context="'.$context.'"', $view, $context);
            $this->assertStringContainsString('data-location-levels', $view, $context);
            $this->assertStringContainsString('name="location_id"', $view, $context);
            $this->assertStringContainsString('name="location_proposal_id"', $view, $context);
            $this->assertStringContainsString('name="reference_settlement_external_id"', $view, $context);
            $this->assertStringContainsString('data-reference-settlement-picker', $view, $context);
            $this->assertStringContainsString('data-reference-settlement-query', $view, $context);
            $this->assertStringContainsString('data-reference-settlement-search', $view, $context);
            $this->assertStringContainsString('data-reference-settlement-results', $view, $context);
            $this->assertStringNotContainsString('data-reference-settlement-neighborhood', $view, $context);
        }

        $this->assertStringContainsString('import "./location-selector.js";', $app);
        $this->assertStringContainsString('import "./registration-settlement-bridge.js";', $app);
        $this->assertStringContainsString("['registration', 'profile', 'admin-user-residence'].includes(selectorContext)", $bridge);

        $this->assertStringContainsString("const isRegistration = context === 'registration';", $core);
        $this->assertStringContainsString('isRegistration ? registrationPayload(normalized) : normalized', $core);
        $this->assertStringContainsString('shouldStopRegistrationAtProposal', $core);
        $this->assertStringContainsString("item?.type_key === 'neighborhood'", $core);
        $this->assertStringContainsString('settlementSelect.value = settlementItem.identity', $core);
        $this->assertStringContainsString('parentReferenceSettlementExternalId', $core);

        $this->assertStringNotContainsString('single_urban_region', $core);
        $this->assertStringNotContainsString('single_neighborhood', $core);
        $this->assertStringNotContainsString('چند منطقه دارد', $core);
        $this->assertStringNotContainsString('چند محله دارد', $core);

        $this->assertStringContainsString('data-location-selector-context="registration"', $registration);
        $this->assertStringNotContainsString('data-location-selector-context="registration"', $profile);
        $this->assertStringNotContainsString('data-location-selector-context="registration"', $admin);
    }

}
