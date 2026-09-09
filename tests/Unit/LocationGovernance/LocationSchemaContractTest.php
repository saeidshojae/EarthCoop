<?php

namespace Tests\Unit\LocationGovernance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_may_branch_to_city_or_rural_district(): void
    {
        $schema = LocationFixture::iranSchema();

        $allowed = LocationFixture::allowedChildTypeKeys($schema, 'section');

        $this->assertContains('city', $allowed);
        $this->assertContains('rural_district', $allowed);
        $this->assertCount(2, $allowed);
    }

    public function test_schema_marks_required_and_optional_residence_endpoint_types(): void
    {
        $schema = LocationFixture::iranSchema();

        $endpointKeys = $schema->types
            ->filter(fn ($type) => (bool) $type->pivot->is_residence_endpoint)
            ->pluck('key')
            ->sort()
            ->values()
            ->all();

        $this->assertContains('city', $endpointKeys);
        $this->assertContains('village', $endpointKeys);
        $this->assertContains('neighborhood', $endpointKeys);
        $this->assertContains('street', $endpointKeys);
        $this->assertContains('alley', $endpointKeys);
        $this->assertContains('complex', $endpointKeys);
        $this->assertContains('building', $endpointKeys);
        $this->assertNotContains('section', $endpointKeys);
        $this->assertNotContains('rural_district', $endpointKeys);
    }
}
