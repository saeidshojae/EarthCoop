<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class GeolocationAssistUiContractTest extends TestCase
{
    public function test_canonical_location_surfaces_offer_geolocation_assist_and_manual_selection_side_by_side(): void
    {
        foreach ([
            resource_path('views/auth/register_step3_canonical.blade.php'),
            resource_path('views/profile/partials/location_canonical.blade.php'),
        ] as $view) {
            $source = file_get_contents($view);

            $this->assertStringContainsString('data-location-geolocation', $source);
            $this->assertStringContainsString('data-location-geolocation-detect', $source);
            $this->assertStringContainsString('تشخیص موقعیت من', $source);
            $this->assertStringContainsString('انتخاب دستی', $source);
        }
    }

    public function test_geolocation_runtime_is_explicit_opt_in_non_blocking_and_does_not_persist_raw_coordinates(): void
    {
        $runtime = resource_path('js/location-geolocation.js');
        $this->assertFileExists($runtime);

        $source = file_get_contents($runtime);
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('location-geolocation.js', $app);
        $this->assertStringContainsString('navigator.geolocation.getCurrentPosition', $source);
        $this->assertStringContainsString("addEventListener('click'", $source);
        $this->assertStringContainsString('/location-governance/geolocation/match', $source);
        $this->assertStringContainsString('manual_location_id', $source);
        $this->assertStringNotContainsString('localStorage', $source);
        $this->assertStringNotContainsString('sessionStorage', $source);
        $this->assertStringNotContainsString('.value = data.suggested_location_id', $source);
    }
}
