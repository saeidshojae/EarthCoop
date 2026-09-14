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

        foreach ([$registrationWrapper, $profileWrapper] as $wrapper) {
            $this->assertStringContainsString("config('location-governance.registration_enabled')", $wrapper);
        }

        $registrationPath = resource_path('views/auth/register_step3_canonical.blade.php');
        $profilePath = resource_path('views/profile/partials/location_canonical.blade.php');

        $this->assertFileExists($registrationPath);
        $this->assertFileExists($profilePath);

        $registration = file_get_contents($registrationPath);
        $profile = file_get_contents($profilePath);

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
    public function shared_selector_consumes_schema_driven_picker_contract_and_proposal_endpoint(): void
    {
        $selectorPath = resource_path('js/location-selector.js');
        $this->assertFileExists($selectorPath);

        $selector = file_get_contents($selectorPath);
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('/location/options/root', $selector);
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
    public function registration_picker_does_not_force_iran_and_can_start_from_global_country_roots(): void
    {
        $registration = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $selector = file_get_contents(resource_path('js/location-selector.js'));

        $this->assertIsString($registration);
        $this->assertIsString($selector);
        $this->assertStringNotContainsString('data-country-code="IR"', $registration);
        $this->assertStringNotContainsString("|| 'IR'", $selector);
        $this->assertStringContainsString('/location/options/root', $selector);
    }

    #[Test]
    public function canonical_profile_edit_does_not_require_a_legacy_address_to_render(): void
    {
        $controllerPath = app_path('Http/Controllers/LocationGovernance/ProfileEditController.php');
        $this->assertFileExists($controllerPath);

        $controller = file_get_contents($controllerPath);
        $routes = file_get_contents(base_path('routes/location-governance.php'));

        $this->assertStringContainsString("config('location-governance.registration_enabled')", $controller);
        $this->assertStringContainsString('locationRelationships()', $controller);
        $this->assertStringContainsString("relationship_type', 'primary_residence'", $controller);
        $this->assertStringContainsString("/profile/edit", $routes);
        $this->assertStringContainsString('ProfileEditController', $routes);
    }
}
