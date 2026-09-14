<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationProposalService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationProposalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_metadata_controls_which_missing_child_types_are_crowdsourcable(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);

        $section = $path->firstWhere('level', 'section');
        $neighborhood = $path->last();
        $cityType = $schema->types->firstWhere('key', 'city');
        $streetType = $schema->types->firstWhere('key', 'street');
        $policy = app(LocationProposalPolicy::class);

        $this->assertFalse($policy->allows($section, $cityType));
        $this->assertTrue($policy->allows($neighborhood, $streetType));
    }

    public function test_service_rejects_a_direct_proposal_for_a_type_forbidden_by_policy(): void
    {
        $schema = LocationFixture::iranSchema();
        $section = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section',
        ])->last();
        $cityType = $schema->types->firstWhere('key', 'city');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Crowdsourced proposals are not permitted');

        app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $section,
            $cityType,
            ['canonical_name' => 'شهر پیشنهادی غیرمجاز'],
        );
    }
}
