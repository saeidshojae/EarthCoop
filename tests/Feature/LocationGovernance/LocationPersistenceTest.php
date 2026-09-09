<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationSchema;
use App\Models\LocationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_persists_tree_identity_names_provenance_and_external_ids(): void
    {
        $schema = LocationSchema::factory()->create([
            'key' => 'ir-reference-v1',
            'country_code' => 'IR',
            'version' => '1',
        ]);

        $countryType = LocationType::factory()->create(['key' => 'country']);
        $cityType = LocationType::factory()->create(['key' => 'city']);

        $country = Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'canonical_name' => 'Iran',
        ]);

        $city = Location::factory()->create([
            'parent_id' => $country->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $cityType->id,
            'country_code' => 'IR',
            'canonical_name' => 'Sari',
            'localized_names' => ['fa' => 'ساری'],
            'status' => 'active',
            'centroid_latitude' => 36.5659,
            'centroid_longitude' => 53.0586,
            'valid_from' => '2026-09-10',
            'provenance' => ['source' => 'earthcoop-reference', 'dataset_version' => 'ir-v1'],
        ]);

        LocationExternalId::create([
            'location_id' => $city->id,
            'source' => 'official-statistical',
            'dataset_version' => 'ir-v1',
            'external_id' => 'IR-SARI-001',
            'metadata' => ['kind' => 'reference'],
        ]);

        $fresh = $city->fresh(['parent', 'type', 'schema', 'externalIds']);

        $this->assertSame($country->id, $fresh->parent->id);
        $this->assertSame('city', $fresh->type->key);
        $this->assertSame('ir-reference-v1', $fresh->schema->key);
        $this->assertSame('ساری', $fresh->localized_names['fa']);
        $this->assertSame('earthcoop-reference', $fresh->provenance['source']);
        $this->assertSame('IR-SARI-001', $fresh->externalIds->sole()->external_id);
        $this->assertNull($fresh->valid_to);
    }

    public function test_external_identity_does_not_depend_on_display_name(): void
    {
        $location = Location::factory()->create(['canonical_name' => 'Sari']);

        $external = LocationExternalId::create([
            'location_id' => $location->id,
            'source' => 'official-statistical',
            'dataset_version' => 'ir-v1',
            'external_id' => 'IR-SARI-001',
        ]);

        $location->update(['canonical_name' => 'Sari City']);

        $this->assertSame('IR-SARI-001', $external->fresh()->external_id);
        $this->assertSame($location->id, $external->fresh()->location_id);
    }
}
