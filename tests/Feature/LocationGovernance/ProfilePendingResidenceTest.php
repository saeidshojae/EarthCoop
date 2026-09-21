<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProfilePendingResidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
    }

    public function test_proposal_under_current_anchor_sets_pending_intent_without_transfer(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame($proposal->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
    }

    public function test_proposal_under_different_approved_anchor_performs_one_real_transfer_then_sets_intent(): void
    {
        $schema = LocationFixture::iranSchema();
        $oldHome = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['Old Iran', 'Old Province', 'Old County', 'Old Section', 'Old City'],
        )->last();
        $newAnchor = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['New Iran', 'New Province', 'New County', 'New Section', 'New City', 'New Region', 'New Neighborhood'],
        )->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $oldHome, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($user, $newAnchor, $streetType, [
            'canonical_name' => 'خیابان محل جدید پیشنهادی',
        ]);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();

        $this->assertSame($newAnchor->id, $current->location_id);
        $this->assertTrue((bool) $current->explicit_transfer);
        $this->assertSame('profile_pending_residence_anchor', $current->change_reason);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
        $this->assertSame(1, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
    }

    public function test_pending_direct_street_under_city_requires_and_persists_complete_structural_claim_chain(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $regionClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $neighborhoodClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $proposal = LocationProposal::query()->create([
            'parent_location_id' => $city->id,
            'location_schema_id' => $schema->id,
            'country_code' => 'IR',
            'location_type_id' => $streetType->id,
            'canonical_name' => 'خیابان مستقیم پیشنهادی پروفایل',
            'normalized_name' => 'خیابان مستقیم پیشنهادی پروفایل',
            'localized_names' => ['fa' => 'خیابان مستقیم پیشنهادی پروفایل'],
            'status' => \App\Enums\LocationGovernance\LocationProposalStatus::Pending,
            'proposer_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$regionClaim->id, $neighborhoodClaim->id],
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $current->location_id);
        $this->assertEqualsCanonicalizing(
            [$regionClaim->id, $neighborhoodClaim->id],
            $current->metadata['structural_claim_ids']
        );
        $this->assertTrue($regionClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertTrue($neighborhoodClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertSame($proposal->id, $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id);
    }

    public function test_pending_structural_proposal_refreshes_claims_when_profile_anchor_is_unchanged(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $city, ['source' => 'test']);

        $regionClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $neighborhoodClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $streetType,
            ['canonical_name' => 'خیابان ساختاری پیشنهادی'],
            [$regionClaim, $neighborhoodClaim],
        );

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$regionClaim->id, $neighborhoodClaim->id],
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $current->location_id);
        $this->assertEqualsCanonicalizing(
            [$regionClaim->id, $neighborhoodClaim->id],
            $current->metadata['structural_claim_ids']
        );
        $this->assertTrue($regionClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertTrue($neighborhoodClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertSame(0, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
        $this->assertSame($proposal->id, $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id);
    }

    public function test_selecting_current_approved_location_cancels_old_pending_intent_without_transfer(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();
        $intent = app(ResidenceService::class)->setPendingResidenceIntent($user, $proposal, ['source' => 'test']);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_id' => $anchor->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertSame(0, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
        $this->assertSame($anchor->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
    }

    public function test_rejected_proposal_cannot_change_profile_residence(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();
        app(LocationProposalService::class)->reject($proposal, User::factory()->create(), 'rejected');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update.address'), [
                'location_proposal_id' => $proposal->id,
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('location_proposal_id');
        $this->assertSame($anchor->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
        $this->assertSame(0, $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->count());
    }

    public function test_profile_edit_shows_approved_anchor_and_pending_exact_location_as_distinct_states(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();
        app(ResidenceService::class)->setPendingResidenceIntent($user, $proposal, ['source' => 'test']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee($anchor->canonical_name);
        $response->assertSee($proposal->canonical_name);
        $response->assertSee('در انتظار بررسی');
    }

    private function makeCurrentAnchorScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان دقیق پیشنهادی',
        ]);

        $this->assertNotInstanceOf(Location::class, $proposal);

        return [$user, $anchor, $proposal];
    }
}
