<?php

namespace Tests\Unit\LocationGovernance;

use App\Models\Location;
use App\Services\LocationGovernance\LocationDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationDuplicateDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_same_parent_type_and_normalized_name_before_a_new_proposal_is_created(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ]);

        $street = $path->last();
        $complexType = $schema->types->firstWhere('key', 'complex');
        $existing = Location::factory()->create([
            'parent_id' => $street->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $complexType->id,
            'country_code' => 'IR',
            'name' => 'مجتمع بهارستان',
            'canonical_name' => 'مجتمع بهارستان',
            'level' => 'complex',
            'status' => 'active',
        ]);

        $match = app(LocationDuplicateDetector::class)->findLikelyDuplicate(
            parent: $street,
            type: $complexType,
            canonicalName: '  مجتمع   بهارستان  ',
        );

        $this->assertNotNull($match);
        $this->assertSame($existing->id, $match->id);
    }

    public function test_it_does_not_match_same_name_under_a_different_parent_or_type(): void
    {
        $schema = LocationFixture::iranSchema();
        $firstPath = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ], ['Iran', 'Mazandaran', 'Sari', 'Central', 'Sari', 'Region 1', 'A', 'Street A']);
        $secondStreet = Location::factory()->create([
            'parent_id' => $firstPath->firstWhere('level', 'neighborhood')->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $schema->types->firstWhere('key', 'street')->id,
            'country_code' => 'IR',
            'name' => 'Street B',
            'canonical_name' => 'Street B',
            'level' => 'street',
            'status' => 'active',
        ]);

        Location::factory()->create([
            'parent_id' => $firstPath->last()->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $schema->types->firstWhere('key', 'complex')->id,
            'country_code' => 'IR',
            'name' => 'Shared Name',
            'canonical_name' => 'Shared Name',
            'level' => 'complex',
            'status' => 'active',
        ]);

        $match = app(LocationDuplicateDetector::class)->findLikelyDuplicate(
            parent: $secondStreet,
            type: $schema->types->firstWhere('key', 'complex'),
            canonicalName: 'Shared Name',
        );

        $this->assertNull($match);
    }
}
