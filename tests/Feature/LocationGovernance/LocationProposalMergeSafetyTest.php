<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationProposalMergeSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_rejects_a_canonical_target_outside_the_proposals_parent_branch(): void
    {
        $schema = LocationFixture::iranSchema();
        $firstNeighborhood = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['IR A', 'Province A', 'County A', 'Section A', 'City A', 'Region A', 'Neighborhood A'],
        )->last();
        $secondNeighborhood = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['IR B', 'Province B', 'County B', 'Section B', 'City B', 'Region B', 'Neighborhood B'],
        )->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $service = app(LocationProposalService::class);
        $proposal = $service->propose(
            User::factory()->create(),
            $firstNeighborhood,
            $streetType,
            ['canonical_name' => 'Street A'],
        );
        $wrongBranchTarget = Location::factory()->create([
            'parent_id' => $secondNeighborhood->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $streetType->id,
            'country_code' => 'IR',
            'name' => 'Existing Street B',
            'canonical_name' => 'Existing Street B',
            'level' => 'street',
            'status' => 'active',
        ]);

        $this->expectException(DomainException::class);
        $service->merge($proposal, $wrongBranchTarget, User::factory()->create(), 'wrong branch must not merge');
    }
}
