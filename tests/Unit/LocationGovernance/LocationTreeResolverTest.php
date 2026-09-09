<?php

namespace Tests\Unit\LocationGovernance;

use App\Services\LocationGovernance\LocationTreeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationTreeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_urban_ancestry_is_derived_from_parent_links(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);

        $ancestors = app(LocationTreeResolver::class)->ancestors($path->last());

        $this->assertSame(
            ['country', 'province', 'county', 'section', 'city', 'urban_region'],
            $ancestors->pluck('level')->all()
        );
    }

    public function test_rural_village_path_never_requires_city(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district', 'village',
        ]);
        $village = $path->last();

        $resolver = app(LocationTreeResolver::class);
        $ancestorLevels = $resolver->ancestors($village)->pluck('level')->all();

        $this->assertNotContains('city', $ancestorLevels);
        $this->assertTrue($resolver->residenceEndpointAllowed($village));
    }

    public function test_schema_pivot_controls_residence_endpoint_policy(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $cityType = $schema->types->firstWhere('key', 'city');

        $schema->types()->updateExistingPivot($cityType->id, ['is_residence_endpoint' => false]);

        $this->assertFalse(app(LocationTreeResolver::class)->residenceEndpointAllowed($city->fresh()));
    }
}
