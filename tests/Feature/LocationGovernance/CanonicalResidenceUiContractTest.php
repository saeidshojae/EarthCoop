<?php

namespace Tests\Feature\LocationGovernance;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanonicalResidenceUiContractTest extends TestCase
{
    #[Test]
    public function registration_and_profile_switch_between_canonical_and_legacy_location_views(): void
    {
        $registrationWrapper = file_get_contents(resource_path('views/auth/register_step3.blade.php'));
        $profileWrapper = file_get_contents(resource_path('views/profile/partials/location.blade.php'));
        foreach ([$registrationWrapper, $profileWrapper] as $wrapper) $this->assertStringContainsString("config('location-governance.registration_enabled')", $wrapper);

        $registration = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));
        foreach ([$registration, $profile] as $view) {
            $this->assertStringContainsString('data-location-selector', $view);
            $this->assertStringContainsString('name="location_id"', $view);
            $this->assertStringContainsString('name="location_proposal_id"', $view);
            $this->assertStringContainsString('data-location-proposal-id', $view);
        }
        $this->assertStringContainsString('data-location-selector-context="registration"', $registration);
        $this->assertStringContainsString('data-location-selector-context="profile"', $profile);
    }

    #[Test]
    public function registration_profile_and_admin_share_one_collapsed_exception_panel_contract(): void
    {
        $registration = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));
        $admin = file_get_contents(resource_path('views/admin/user/partials/canonical-residence.blade.php'));
        $selector = file_get_contents(resource_path('js/location-selector-core.js'));
        $settlementBridge = file_get_contents(resource_path('js/registration-settlement-bridge.js'));

        foreach ([
            [$registration, 'registration'],
            [$profile, 'profile'],
            [$admin, 'admin-user-residence'],
        ] as [$view, $context]) {
            $this->assertStringContainsString('data-location-selector', $view);
            $this->assertStringContainsString('data-location-selector-context="'.$context.'"', $view);
            $this->assertStringContainsString('data-reference-settlement-picker', $view);
        }

        $this->assertStringContainsString('dataLocationExceptionToggle', $selector);
        $this->assertStringContainsString('dataLocationExceptionPanel', $selector);
        $this->assertStringContainsString('earthcoop-location-exception-open', $selector);
        $this->assertStringContainsString('earthcoop-location-exception-open', $settlementBridge);
        $this->assertStringContainsString("['registration', 'profile', 'admin-user-residence']", $settlementBridge);
        $this->assertStringContainsString('روستا یا آبادی من در فهرست نیست', $selector);
        $this->assertStringContainsString('خیابان من در فهرست نیست', $selector);
        $this->assertStringNotContainsString('single_urban_region', $selector);
        $this->assertStringNotContainsString('single_neighborhood', $selector);
        $this->assertStringNotContainsString('چند منطقه دارد', $selector);
        $this->assertStringNotContainsString('چند محله دارد', $selector);
    }

    #[Test]
    public function shared_selector_consumes_schema_driven_picker_contract_and_proposal_endpoint(): void
    {
        $selector = file_get_contents(resource_path('js/location-selector-core.js'))
            . file_get_contents(resource_path('js/location-selector.js'));
        $app = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('/location/residence/options/root', $selector);
        $this->assertStringContainsString('/children', $selector);
        $this->assertStringContainsString('/locations/proposals', $selector);
        $this->assertStringContainsString('is_residence_endpoint', $selector);
        $this->assertStringContainsString('allowed_types', $selector);
        $this->assertStringContainsString('location_id', $selector);
        $this->assertStringContainsString('location_proposal_id', $selector);
        $this->assertStringNotContainsString('/api/locations?level=', $selector);
        $this->assertStringContainsString('location-selector.js', $app);
    }

    #[Test]
    public function registration_picker_does_not_force_iran_and_can_start_from_continent_roots(): void
    {
        $registration = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $selector = file_get_contents(resource_path('js/location-selector-core.js'))
            . file_get_contents(resource_path('js/location-selector.js'));
        $this->assertStringNotContainsString('data-country-code="IR"', $registration);
        $this->assertStringNotContainsString("|| 'IR'", $selector);
        $this->assertStringContainsString('/location/residence/options/root', $selector);
    }

    #[Test]
    public function canonical_profile_edit_does_not_require_a_legacy_address_to_render(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/LocationGovernance/ProfileEditController.php'));
        $routes = file_get_contents(base_path('routes/location-governance.php'));
        $this->assertStringContainsString("config('location-governance.registration_enabled')", $controller);
        $this->assertStringContainsString('locationRelationships()', $controller);
        $this->assertStringContainsString("relationship_type', 'primary_residence'", $controller);
        $this->assertStringContainsString("/profile/edit", $routes);
        $this->assertStringContainsString('ProfileEditController', $routes);
    }

    #[Test]
    public function profile_residence_editor_hydrates_current_selection_and_exposes_mobile_first_shared_controls(): void
    {
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));
        $ux = file_get_contents(resource_path('js/registration-location-ux.js'));
        $this->assertStringContainsString('data-location-path', $profile);
        $this->assertStringContainsString('data-location-current-id', $profile);
        $this->assertStringContainsString("old('location_id', \$primaryResidence?->location_id)", $profile);
        $this->assertStringContainsString('location-residence-surface', $profile);
        $this->assertStringContainsString('location-geolocation-actions', $profile);
        $this->assertStringContainsString('location-proposal-help', $profile);
        $this->assertStringContainsString('dataset.locationCurrentId', $ux);
        $this->assertStringContainsString('[data-location-selector-context="profile"]', $ux);
    }

    #[Test]
    public function proposal_affordance_is_explicitly_mobile_touch_friendly_and_only_built_for_server_allowed_types(): void
    {
        $selector = file_get_contents(resource_path('js/location-selector-core.js'))
            . file_get_contents(resource_path('js/location-selector.js'));
        $ux = file_get_contents(resource_path('js/registration-location-ux.js'));
        $this->assertStringContainsString('proposal_allowed === true', $selector);
        $this->assertStringContainsString('dataset.locationProposalShell', $selector);
        $this->assertStringContainsString('location-proposal-surface', $ux);
        $this->assertStringContainsString('location-proposal-toggle', $ux);
        $this->assertStringContainsString('min-height: 44px', $ux);
    }

    public function test_profile_canonical_summary_uses_typed_location_display_names(): void
    {
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));

        $this->assertStringContainsString('LocationDisplayName::typed($primaryResidence->location)', $profile);
        $this->assertStringContainsString('LocationDisplayName::typed($proposal)', $profile);
    }


    public function test_canonical_residence_forms_carry_structural_claim_ids_without_changing_visible_layout(): void
    {
        $selector = file_get_contents(resource_path('js/location-selector-core.js'));

        $this->assertStringContainsString('location_structure_claim_ids[]', $selector);
        $this->assertStringContainsString('data-location-structure-claim-id', $selector);
        $this->assertStringContainsString("input.type = 'hidden'", $selector);
        $this->assertStringContainsString('result.id', $selector);

        $registration = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $profile = file_get_contents(resource_path('views/profile/partials/location_canonical.blade.php'));
        $admin = file_get_contents(resource_path('views/admin/user/partials/canonical-residence.blade.php'));
        foreach ([$registration, $profile, $admin] as $view) {
            $this->assertStringContainsString('name="location_structure_claim_ids[]"', $view);
            $this->assertStringContainsString('data-location-structure-claim-id', $view);
        }

        $wrapper = file_get_contents(resource_path('js/location-selector.js'));
        $this->assertStringContainsString('pendingStructuralClaimContextUrl', $wrapper);
        $this->assertStringContainsString('location_structure_claim_ids: pendingStructuralClaimIds(host)', $wrapper);
        $this->assertStringContainsString('فقط در صورت انتخاب شما روی مسیرتان اعمال می‌شود', $wrapper);
    }

}
