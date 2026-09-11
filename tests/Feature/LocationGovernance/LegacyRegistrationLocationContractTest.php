<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class LegacyRegistrationLocationContractTest extends TestCase
{
    public function test_step3_currently_validates_fixed_government_geography_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Auth/Register/Step3Controller.php'));

        $this->assertStringContainsString("'country_id'", $source);
        $this->assertStringContainsString("'province_id'", $source);
        $this->assertStringContainsString("'county_id'", $source);
        $this->assertStringContainsString("'section_id'", $source);
    }

    public function test_step3_currently_persists_urban_or_rural_leaf_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Auth/Register/Step3Controller.php'));

        $this->assertStringContainsString("'city_id'", $source);
        $this->assertStringContainsString("'rural_id'", $source);
        $this->assertStringContainsString("'region_id'", $source);
        $this->assertStringContainsString("'village_id'", $source);
        $this->assertStringContainsString("'neighborhood_id'", $source);
    }
}
