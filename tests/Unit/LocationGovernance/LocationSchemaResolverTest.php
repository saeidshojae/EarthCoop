<?php

namespace Tests\Unit\LocationGovernance;

use App\Exceptions\InvalidLocationHierarchy;
use App\Models\LocationType;
use App\Services\LocationGovernance\LocationSchemaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationSchemaResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_allows_both_urban_and_rural_branches(): void
    {
        $schema = LocationFixture::iranSchema();
        $section = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section'])->last();

        $keys = app(LocationSchemaResolver::class)
            ->allowedChildTypes($section)
            ->pluck('key')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['city', 'rural_district'], $keys);
    }

    public function test_complex_may_be_directly_under_street_or_under_alley(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'alley',
        ]);

        $complexType = $schema->types->firstWhere('key', 'complex');
        $resolver = app(LocationSchemaResolver::class);

        $resolver->assertValidParentChild($path->firstWhere('level', 'street'), $complexType);
        $resolver->assertValidParentChild($path->firstWhere('level', 'alley'), $complexType);

        $this->addToAssertionCount(2);
    }

    public function test_invalid_parent_child_pair_is_rejected(): void
    {
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'])->last();
        $buildingType = $schema->types->firstWhere('key', 'building');

        $this->expectException(InvalidLocationHierarchy::class);

        app(LocationSchemaResolver::class)->assertValidParentChild($country, $buildingType);
    }

    public function test_child_type_from_another_schema_is_rejected_even_when_key_matches_a_valid_shape(): void
    {
        $schema = LocationFixture::iranSchema();
        $section = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section'])->last();
        $foreignType = LocationType::factory()->create([
            'key' => 'foreign_city_' . uniqid(),
            'canonical_name' => 'Foreign City',
            'is_residence_endpoint' => true,
        ]);

        $this->expectException(InvalidLocationHierarchy::class);

        app(LocationSchemaResolver::class)->assertValidParentChild($section, $foreignType);
    }
}
