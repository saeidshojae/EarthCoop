<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationStructureClaimLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_rejects_claim_type_not_allowed_for_location_type(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        $this->expectException(DomainException::class);
        app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($city, 'no_neighborhood', User::factory()->create());
    }

    public function test_service_allows_contextual_city_neighborhood_claim_after_region_layer_is_collapsed(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);

        $regionClaim = $service->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $neighborhoodClaim = $service->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $this->assertSame('no_urban_region', $regionClaim->claim_type);
        $this->assertSame('no_neighborhood', $neighborhoodClaim->claim_type);
        $this->assertSame($city->id, $neighborhoodClaim->location_id);
        $this->assertSame(2, LocationStructureClaim::query()->where('location_id', $city->id)->whereIn('status', LocationStructureClaimService::OPEN_STATUSES)->count());
    }

    public function test_rejected_claim_is_not_open_and_cannot_receive_committed_support(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();
        $user = User::factory()->create();
        $claim = LocationStructureClaim::create([
            'location_id' => $village->id,
            'claim_type' => 'no_neighborhood',
            'status' => 'rejected',
            'proposer_user_id' => $user->id,
        ]);

        app(LocationStructureClaimService::class)
            ->recordCommittedSupport($claim, $user, ['source' => 'residence_commit']);

        $this->assertSame(0, $claim->fresh()->evidence()->count());
        $this->assertSame('rejected', $claim->fresh()->status);
    }

    public function test_threshold_config_has_explicit_default_and_open_claim_reuse_survives_ready_state(): void
    {
        $config = file_get_contents(config_path('location-governance.php'));
        $this->assertStringContainsString("'location_structure_claim_verification_threshold'", $config);

        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $service = app(LocationStructureClaimService::class);
        $first = $service->findOrCreateOpenClaim($city, 'no_urban_region', User::factory()->create());
        $first->forceFill(['status' => 'ready_for_review'])->save();

        $again = $service->findOrCreateOpenClaim($city, 'no_urban_region', User::factory()->create());

        $this->assertSame($first->id, $again->id);
    }

    public function test_distinct_committed_support_reaches_persisted_threshold_but_never_auto_approves_claim(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();
        $service = app(LocationStructureClaimService::class);

        $settings = \App\Models\Setting::singleton();
        $settings->forceFill(['location_structure_claim_verification_threshold' => 2])->save();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $claim = $service->findOrCreateOpenClaim($village, 'no_neighborhood', $firstUser);

        $service->recordCommittedSupport($claim, $firstUser, ['source' => 'residence_commit']);
        $this->assertSame('pending', $claim->fresh()->status);

        $service->recordCommittedSupport($claim, $secondUser, ['source' => 'residence_commit']);

        $claim->refresh();
        $this->assertSame('ready_for_review', $claim->status);
        $this->assertNull($claim->reviewed_by_user_id);
        $this->assertNull($claim->approved_at);
        $this->assertSame(2, $claim->evidence()->distinct()->count('user_id'));
    }

}
