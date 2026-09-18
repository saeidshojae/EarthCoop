<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationStructureClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_structural_claim_is_reused_without_creating_fake_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $before = Location::query()->count();
        $service = app(LocationStructureClaimService::class);

        $first = $service->findOrCreateOpenClaim($city, 'single_urban_region', User::factory()->create());
        $second = $service->findOrCreateOpenClaim($city, 'single_urban_region', User::factory()->create());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($before, Location::query()->count());
        $this->assertSame('pending', $first->status);
        $this->assertSame(1, LocationStructureClaim::query()->count());
    }

    public function test_distinct_committed_support_is_idempotent_and_threshold_never_auto_approves(): void
    {
        config(['location-governance.location_structure_claim_verification_threshold' => 3]);

        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'rural_district', 'village'])->last();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($village, 'no_neighborhood', User::factory()->create());
        $first = User::factory()->create();

        $service->recordCommittedSupport($claim, $first, ['source' => 'residence_commit']);
        $service->recordCommittedSupport($claim->fresh(), $first, ['source' => 'residence_commit_updated']);
        $this->assertSame(1, $claim->fresh()->evidence()->distinct()->count('user_id'));
        $this->assertSame('pending', $claim->fresh()->status);

        $service->recordCommittedSupport($claim->fresh(), User::factory()->create(), ['source' => 'residence_commit']);
        $service->recordCommittedSupport($claim->fresh(), User::factory()->create(), ['source' => 'residence_commit']);

        $claim->refresh();
        $this->assertSame(3, $claim->evidence()->distinct()->count('user_id'));
        $this->assertSame('ready_for_review', $claim->status);
        $this->assertNull($claim->approved_at);
    }    public function test_authenticated_resident_can_create_or_reuse_an_allowed_structural_claim_via_http(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/locations/structure-claims', [
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
        ]);

        $response->assertOk()
            ->assertJsonPath('location_id', $city->id)
            ->assertJsonPath('claim_type', 'no_urban_region')
            ->assertJsonPath('status', 'pending');

        $this->actingAs($user)->postJson('/locations/structure-claims', [
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
        ])->assertOk()->assertJsonPath('id', $response->json('id'));
    }
}