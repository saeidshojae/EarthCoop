<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class RegistrationStructuralClaimEntryPointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['location-governance.runtime_enabled'=>true,'location-governance.registration_enabled'=>true]);
    }

    public function test_registration_accepts_open_structural_claim_with_canonical_location_and_commits_support(): void
    {
        $schema=LocationFixture::iranSchema();
        $city=LocationFixture::createPath($schema,['country','province','county','section','city'])->last();
        $user=User::factory()->create();
        $service=app(LocationStructureClaimService::class);
        $noRegion=$service->findOrCreateOpenClaim($city,'no_urban_region',$user);
        $noNeighborhood=$service->findOrCreateOpenClaim($city,'no_neighborhood',$user);

        $this->actingAs($user)->post(route('register.step3.process'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$noRegion->id,$noNeighborhood->id],
        ])->assertRedirect(route('home'));

        $relationship=$user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertEqualsCanonicalizing([$noRegion->id,$noNeighborhood->id],$relationship->metadata['structural_claim_ids']);
        $this->assertTrue($noRegion->fresh()->evidence()->where('user_id',$user->id)->exists());
        $this->assertTrue($noNeighborhood->fresh()->evidence()->where('user_id',$user->id)->exists());
    }

    public function test_registration_rejects_structural_claim_belonging_to_another_location(): void
    {
        $schema=LocationFixture::iranSchema();
        $path=LocationFixture::createPath($schema,['country','province','county','section','city']);
        $city=$path->last();
        $other=LocationFixture::createPath($schema,['country','province','county','section','city'], ['کشور دوم','استان دوم','شهرستان دوم','بخش دوم','شهر دیگر'])->last();
        $user=User::factory()->create();
        $service=app(LocationStructureClaimService::class);
        $noRegion=$service->findOrCreateOpenClaim($city,'no_urban_region',$user);
        $noNeighborhood=$service->findOrCreateOpenClaim($city,'no_neighborhood',$user);
        $claim=$service->findOrCreateOpenClaim($other,'no_urban_region',$user);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$noRegion->id,$noNeighborhood->id,$claim->id],
        ])->assertSessionHasErrors('location_structure_claim_ids');

        $this->assertSame(0,$user->fresh()->locationRelationships()->count());
        $this->assertSame(0,$claim->fresh()->evidence()->count());
    }

    public function test_registration_stops_at_structural_governance_base_instead_of_micro_location(): void
    {
        $schema=LocationFixture::iranSchema();
        $city=LocationFixture::createPath($schema,['country','province','county','section','city'])->last();
        $user=User::factory()->create();
        $service=app(LocationStructureClaimService::class);
        $noRegion=$service->findOrCreateOpenClaim($city,'no_urban_region',$user);
        $noNeighborhood=$service->findOrCreateOpenClaim($city,'no_neighborhood',$user);

        $this->actingAs($user)->post(route('register.step3.process'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$noRegion->id,$noNeighborhood->id],
        ])->assertRedirect(route('home'));

        $relationship=$user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame($city->id,$relationship->location_id);
        $this->assertEqualsCanonicalizing([$noRegion->id,$noNeighborhood->id],$relationship->metadata['structural_claim_ids']);
        $this->assertTrue($noRegion->fresh()->evidence()->where('user_id',$user->id)->exists());
        $this->assertTrue($noNeighborhood->fresh()->evidence()->where('user_id',$user->id)->exists());
    }

    public function test_registration_rejects_pending_micro_detail_even_with_structural_claims(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $regionClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $neighborhoodClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $proposal = \App\Models\LocationProposal::query()->create([
            'parent_location_id' => $city->id,
            'location_schema_id' => $schema->id,
            'country_code' => 'IR',
            'location_type_id' => $streetType->id,
            'canonical_name' => 'خیابان پیشنهادی مستقیم',
            'normalized_name' => 'خیابان پیشنهادی مستقیم',
            'localized_names' => ['fa' => 'خیابان پیشنهادی مستقیم'],
            'status' => \App\Enums\LocationGovernance\LocationProposalStatus::Pending,
            'proposer_user_id' => $user->id,
        ]);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$regionClaim->id, $neighborhoodClaim->id],
        ])->assertRedirect(route('register.step3'))->assertSessionHasErrors('location_proposal_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertSame(0, $user->fresh()->pendingResidenceIntents()->count());
    }

    public function test_registration_cannot_treat_sparse_city_as_base_without_explicit_absence_claim(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'location_id' => $city->id,
        ])->assertRedirect(route('register.step3'))->assertSessionHasErrors('location_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
    }

    public function test_registration_single_region_claim_requires_selecting_the_real_region(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region']);
        $city = $path->first(fn ($location) => $location->type?->key === 'city');
        $region = $path->last();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($city, 'single_urban_region', $user);
        $noNeighborhood = $service->findOrCreateOpenClaim($region, 'no_neighborhood', $user);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'location_id' => $city->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertSessionHasErrors('location_id');

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_id' => $region->id,
            'location_structure_claim_ids' => [$claim->id, $noNeighborhood->id],
        ])->assertRedirect(route('home'));
    }


    public function test_registration_accepts_pending_neighborhood_directly_below_city_with_open_no_region_claim(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $proposal = app(\App\Services\LocationGovernance\LocationProposalService::class)->propose(
            $user,
            $city,
            $neighborhoodType,
            [
                'canonical_name' => 'محله آزمایشی کیاسر',
                'localized_names' => ['fa' => 'محله آزمایشی کیاسر'],
            ],
            [$claim],
        );

        $this->assertInstanceOf(\App\Models\LocationProposal::class, $proposal);
        $this->assertSame([$claim->id], data_get($proposal->metadata, 'structural_claim_ids'));

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertRedirect(route('home'));

        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $relationship->location_id);
        $this->assertEqualsCanonicalizing([$claim->id], $relationship->metadata['structural_claim_ids']);
        $this->assertDatabaseHas('pending_residence_intents', [
            'user_id' => $user->id,
            'location_proposal_id' => $proposal->id,
            'status' => 'pending',
        ]);
        $this->assertSame(1, $proposal->fresh()->evidence()->distinct()->count('user_id'));
        $proposalEvidence = $proposal->fresh()->evidence()->where('user_id', $user->id)->sole();
        $this->assertSame('residence_commit', data_get($proposalEvidence->evidence, 'source'));
    }

    public function test_registration_accepts_pending_region_or_village_without_neighborhood_and_keeps_proposal_claim_separate_from_canonical_anchor(): void
    {
        $schema = LocationFixture::iranSchema();

        foreach ([
            [
                'parent_path' => ['country','province','county','section','city'],
                'proposal_type' => 'urban_region',
                'name' => 'منطقه پیشنهادی بدون محله',
            ],
            [
                'parent_path' => ['country','province','county','section','rural_district'],
                'proposal_type' => 'village',
                'name' => 'روستای پیشنهادی بدون محله',
            ],
        ] as $scenario) {
            $anchor = LocationFixture::createPath($schema, $scenario['parent_path'])->last();
            $type = $schema->types->firstWhere('key', $scenario['proposal_type']);
            $user = User::factory()->create();

            $proposal = app(\App\Services\LocationGovernance\LocationProposalService::class)->propose(
                $user,
                $anchor,
                $type,
                ['canonical_name' => $scenario['name']],
            );
            $this->assertInstanceOf(\App\Models\LocationProposal::class, $proposal);

            $claimResponse = $this->actingAs($user)->postJson(
                '/location/proposals/'.$proposal->id.'/structure-claims',
                ['claim_type' => 'no_neighborhood'],
            )->assertCreated();
            $claimId = (int) $claimResponse->json('id');

            $this->actingAs($user)->post(route('register.step3.process'), [
                'location_proposal_id' => $proposal->id,
                'location_structure_claim_ids' => [$claimId],
            ])->assertRedirect(route('home'));

            $relationship = $user->fresh()->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->sole();
            $intent = $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();
            $claim = \App\Models\LocationStructureClaim::query()->findOrFail($claimId);

            $this->assertSame($anchor->id, $relationship->location_id);
            $this->assertSame([], collect($relationship->metadata['structural_claim_ids'] ?? [])->values()->all());
            $this->assertSame([$claimId], collect($intent->metadata['structural_claim_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all());
            $this->assertTrue($claim->evidence()->where('user_id', $user->id)->exists());
            $this->assertTrue($proposal->fresh()->evidence()->where('user_id', $user->id)->exists());
        }
    }

    public function test_city_without_region_must_continue_to_real_neighborhood_before_registration_can_finish(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $neighborhood = \App\Models\Location::create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $neighborhoodType->id,
            'parent_id' => $city->id,
            'country_code' => 'IR',
            'canonical_name' => 'Direct Neighborhood',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'location_id' => $city->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertSessionHasErrors('location_id');

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_id' => $neighborhood->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertRedirect(route('home'));
    }

    public function test_city_without_region_and_without_neighborhood_can_finish_registration_at_city(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);
        $noRegion = $service->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $service->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_id' => $city->id,
            'location_structure_claim_ids' => [$noRegion->id, $noNeighborhood->id],
        ])->assertRedirect(route('home'));

        $relationship = $user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame($city->id, $relationship->location_id);
        $this->assertEqualsCanonicalizing([$noRegion->id, $noNeighborhood->id], $relationship->metadata['structural_claim_ids']);
    }


    public function test_region_and_village_require_explicit_no_neighborhood_claim_to_finish_registration(): void
    {
        $schema = LocationFixture::iranSchema();
        $service = app(LocationStructureClaimService::class);

        foreach ([
            ['country','province','county','section','city','urban_region'],
            ['country','province','county','section','rural_district','village'],
        ] as $pathTypes) {
            $location = LocationFixture::createPath($schema, $pathTypes)->last();
            $user = User::factory()->create();

            $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
                'location_id' => $location->id,
            ])->assertSessionHasErrors('location_id');

            $claim = $service->findOrCreateOpenClaim($location, 'no_neighborhood', $user);

            $this->actingAs($user)->post(route('register.step3.process'), [
                'location_id' => $location->id,
                'location_structure_claim_ids' => [$claim->id],
            ])->assertRedirect(route('home'));
        }
    }


}
