<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationSelectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_governance_rollout_flags_default_to_false(): void
    {
        $this->assertFalse((bool) config('location-governance.runtime_enabled'));
        $this->assertFalse((bool) config('location-governance.registration_enabled'));
        $this->assertFalse((bool) config('location-governance.groups_enabled'));
        $this->assertFalse((bool) config('location-governance.elections_enabled'));
    }

    public function test_root_options_are_schema_driven_localized_and_hide_pending_locations(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');

        $schema = LocationFixture::iranSchema();
        $countryType = $schema->types->firstWhere('key', 'country');

        $active = Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran',
            'canonical_name' => 'Iran',
            'localized_names' => ['fa' => 'ایران'],
            'level' => 'country',
            'status' => 'active',
        ]);

        Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Pending Iran root',
            'canonical_name' => 'Pending Iran root',
            'localized_names' => ['fa' => 'ایران در انتظار بررسی'],
            'level' => 'country',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/location/options/root?country=IR');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $active->id);
        $response->assertJsonPath('data.0.type_key', 'country');
        $response->assertJsonPath('data.0.label', 'ایران');
        $response->assertJsonPath('data.0.is_residence_endpoint', false);
        $response->assertJsonPath('data.0.has_children', false);
        $response->assertJsonPath('data.0.status', 'active');
    }

    public function test_children_follow_schema_branching_and_report_endpoint_and_child_state(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section'],
        );
        $section = $path->last();

        $cityType = $schema->types->firstWhere('key', 'city');
        $ruralType = $schema->types->firstWhere('key', 'rural_district');
        $urbanRegionType = $schema->types->firstWhere('key', 'urban_region');

        $city = Location::factory()->create([
            'parent_id' => $section->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $cityType->id,
            'country_code' => 'IR',
            'name' => 'Sari',
            'canonical_name' => 'Sari',
            'level' => 'city',
            'status' => 'active',
        ]);
        Location::factory()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $urbanRegionType->id,
            'country_code' => 'IR',
            'name' => 'Region 1',
            'canonical_name' => 'Region 1',
            'level' => 'urban_region',
            'status' => 'active',
        ]);

        $rural = Location::factory()->create([
            'parent_id' => $section->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $ruralType->id,
            'country_code' => 'IR',
            'name' => 'Chahardangeh',
            'canonical_name' => 'Chahardangeh',
            'level' => 'rural_district',
            'status' => 'active',
        ]);

        Location::factory()->create([
            'parent_id' => $section->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $cityType->id,
            'country_code' => 'IR',
            'name' => 'Pending City',
            'canonical_name' => 'Pending City',
            'level' => 'city',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/location/options/'.$section->id.'/children');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertSame('city', $rows[$city->id]['type_key']);
        $this->assertTrue($rows[$city->id]['is_residence_endpoint']);
        $this->assertTrue($rows[$city->id]['has_children']);
        $this->assertSame('active', $rows[$city->id]['status']);

        $this->assertSame('rural_district', $rows[$rural->id]['type_key']);
        $this->assertFalse($rows[$rural->id]['is_residence_endpoint']);
        $this->assertFalse($rows[$rural->id]['has_children']);
    }
}
