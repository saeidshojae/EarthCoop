<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationStructureClaim;
use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ResidencePickerDeepHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_fa_allowed_type_labels_are_localized_for_every_known_residence_type(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);
        $city = $path->last();

        $response = $this->getJson('/location/options/'.$city->id.'/children');

        $response->assertOk();
        $response->assertJsonPath('allowed_types.0.key', 'urban_region');
        $response->assertJsonPath('allowed_types.0.label', 'منطقه');
    }

    public function test_structural_claim_chain_exposes_only_the_real_next_residence_level(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);

        $regionClaim = $service->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $defaultAfterRegion = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $this->assertSame(['urban_region'], collect($defaultAfterRegion->json('effective_allowed_types'))->pluck('key')->all());

        $afterRegion = $this->getJson('/location/options/'.$city->id.'/children?'.http_build_query([
            'location_structure_claim_ids' => [$regionClaim->id],
        ]))->assertOk();
        $this->assertSame(['neighborhood'], collect($afterRegion->json('effective_allowed_types'))->pluck('key')->all());

        $neighborhoodClaim = $service->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $afterNeighborhood = $this->getJson('/location/options/'.$city->id.'/children?'.http_build_query([
            'location_structure_claim_ids' => [$regionClaim->id, $neighborhoodClaim->id],
        ]))->assertOk();
        $afterNeighborhood->assertJsonPath('effective_allowed_types.0.key', 'street');
        $afterNeighborhood->assertJsonPath('effective_allowed_types.0.proposal_allowed', true);
        $this->assertSame(['street'], collect($afterNeighborhood->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertSame($regionClaim->id, collect($afterNeighborhood->json('structural_choices'))->firstWhere('claim_type', 'no_urban_region')['claim_id']);
        $this->assertSame($neighborhoodClaim->id, collect($afterNeighborhood->json('structural_choices'))->firstWhere('claim_type', 'no_neighborhood')['claim_id']);
    }

    public function test_region_and_village_without_neighborhood_expose_street_for_pending_and_approved_claims(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);
        $region = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region'])->last();
        $village = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'rural_district', 'village'])->last();

        $regionClaim = $service->findOrCreateOpenClaim($region, 'no_neighborhood', $user);
        $villageClaim = $service->findOrCreateOpenClaim($village, 'no_neighborhood', $user);

        foreach ([[$region, $regionClaim], [$village, $villageClaim]] as [$location, $claim]) {
            $default = $this->getJson('/location/options/'.$location->id.'/children')->assertOk();
            $this->assertSame(['neighborhood'], collect($default->json('effective_allowed_types'))->pluck('key')->all());

            $response = $this->getJson('/location/options/'.$location->id.'/children?'.http_build_query([
                'location_structure_claim_ids' => [$claim->id],
            ]))->assertOk();
            $this->assertSame(['street'], collect($response->json('effective_allowed_types'))->pluck('key')->all());
            $response->assertJsonPath('effective_allowed_types.0.proposal_allowed', true);
        }

        $regionClaim->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
        $villageClaim->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        foreach ([$region, $village] as $location) {
            $response = $this->getJson('/location/options/'.$location->id.'/children');
            $response->assertOk();
            $this->assertSame(['street'], collect($response->json('effective_allowed_types'))->pluck('key')->all());
        }
    }

    public function test_location_proposals_have_an_additive_nullable_proposal_parent_column(): void
    {
        $this->assertTrue(Schema::hasColumn('location_proposals', 'parent_location_proposal_id'));
    }

    public function test_open_proposal_can_parent_a_deeper_open_proposal_without_fabricating_a_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);

        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $alley = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);

        $this->assertInstanceOf(LocationProposal::class, $alley);
        $this->assertNull($alley->parent_location_id);
        $this->assertSame($street->id, $alley->parent_location_proposal_id);
        $this->assertSame($schema->id, $alley->location_schema_id);
        $this->assertSame('IR', $alley->country_code);
        $this->assertSame(LocationProposalStatus::Pending, $alley->status);
    }

    public function test_repeated_same_canonical_proposal_reuses_one_open_record(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);

        $first = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان تکرارنشدنی']);
        $second = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان تکرارنشدنی']);

        $this->assertInstanceOf(LocationProposal::class, $first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LocationProposal::query()->where('parent_location_id', $neighborhood->id)->where('location_type_id', $streetType->id)->where('normalized_name', $first->normalized_name)->count());
    }

    public function test_proposal_parent_reuses_same_open_child_and_rejects_invalid_type_relation(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $provinceType = $schema->types->firstWhere('key', 'province');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);

        $first = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه الف']);
        $second = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه الف']);
        $this->assertSame($first->id, $second->id);

        $this->expectException(DomainException::class);
        $service->proposeUnderProposal($user, $street, $provinceType, ['canonical_name' => 'استان نامعتبر']);
    }

    public function test_approving_parent_reanchors_direct_open_children_without_auto_approving_them(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $alley = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);

        $approvedStreet = $service->approve($street, $reviewer, 'uat contract');
        $alley->refresh();

        $this->assertSame($approvedStreet->id, $alley->parent_location_id);
        $this->assertNull($alley->parent_location_proposal_id);
        $this->assertSame(LocationProposalStatus::Pending, $alley->status);
    }

    public function test_rejecting_parent_with_open_descendants_is_blocked(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);

        $this->expectException(DomainException::class);
        $service->reject($street, $reviewer, 'cannot orphan child');
    }

    public function test_proposal_children_endpoint_exposes_deeper_pending_options_with_persian_labels(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $street = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);

        $response = $this->actingAs($user)->getJson('/location/proposals/'.$street->id.'/children');

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('allowed_types.0.key', 'alley');
        $response->assertJsonPath('allowed_types.0.label', 'کوچه');
        $response->assertJsonPath('allowed_types.0.proposal_allowed', true);
    }

    public function test_store_accepts_exactly_one_parent_kind_for_deeper_proposal(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $street = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);

        $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_proposal_id' => $street->id,
            'location_type_id' => $alleyType->id,
            'canonical_name' => 'کوچه جدید',
            'localized_names' => ['fa' => 'کوچه جدید'],
        ])->assertCreated()->assertJsonPath('kind', 'proposal');

        $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_id' => $neighborhood->id,
            'parent_location_proposal_id' => $street->id,
            'location_type_id' => $alleyType->id,
            'canonical_name' => 'والد مبهم',
        ])->assertUnprocessable();
    }

    public function test_direct_street_proposal_under_city_requires_complete_structural_claim_chain(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $claimService = app(\App\Services\LocationGovernance\LocationStructureClaimService::class);

        $noRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_id' => $city->id,
            'location_type_id' => $streetType->id,
            'canonical_name' => 'خیابان مستقیم بدون ادعا',
        ])->assertStatus(422);

        $response = $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_id' => $city->id,
            'location_type_id' => $streetType->id,
            'canonical_name' => 'خیابان مستقیم ساختاری',
            'location_structure_claim_ids' => [$noRegion->id, $noNeighborhood->id],
        ])->assertSuccessful()->assertJsonPath('kind', 'proposal');

        $proposal = \App\Models\LocationProposal::query()->findOrFail((int) $response->json('id'));
        $this->assertSame([$noRegion->id, $noNeighborhood->id], $proposal->metadata['structural_claim_ids']);
    }

    public function test_second_user_can_create_structural_street_proposal_with_shared_open_city_claims(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $claimService = app(LocationStructureClaimService::class);

        $noRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $firstUser);
        $noNeighborhood = $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $firstUser);

        $this->assertSame(
            $noRegion->id,
            $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $secondUser)->id,
        );
        $this->assertSame(
            $noNeighborhood->id,
            $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $secondUser)->id,
        );

        $response = $this->actingAs($secondUser)->postJson('/locations/proposals', [
            'parent_location_id' => $city->id,
            'location_type_id' => $streetType->id,
            'canonical_name' => 'خیابان پیشنهادی کاربر دوم',
            'location_structure_claim_ids' => [$noRegion->id, $noNeighborhood->id],
        ])->assertCreated()->assertJsonPath('kind', 'proposal');

        $proposal = LocationProposal::query()->findOrFail((int) $response->json('id'));
        $this->assertSame($secondUser->id, $proposal->proposer_user_id);
        $this->assertSame([$noRegion->id, $noNeighborhood->id], $proposal->metadata['structural_claim_ids']);
    }

    public function test_structural_street_proposal_is_revalidated_and_preserves_claim_provenance_on_approval(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $reviewer = User::factory()->create(['is_admin' => true]);
        $claimService = app(LocationStructureClaimService::class);
        $proposalService = app(LocationProposalService::class);

        $noRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $proposal = $proposalService->propose($user, $city, $streetType, [
            'canonical_name' => 'خیابان مستقیم قابل تأیید',
        ], [$noRegion, $noNeighborhood]);

        $approved = $proposalService->approve($proposal, $reviewer, 'ساختار شهر بررسی شد');

        $this->assertSame($city->id, $approved->parent_id);
        $this->assertSame('street', $approved->level);
        $this->assertSame($proposal->id, data_get($approved->provenance, 'location_proposal_id'));
        $this->assertSame(
            [$noRegion->id, $noNeighborhood->id],
            data_get($approved->provenance, 'structural_claim_ids')
        );
    }

    public function test_structural_street_proposal_cannot_be_approved_after_supporting_claim_is_rejected(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $reviewer = User::factory()->create(['is_admin' => true]);
        $claimService = app(LocationStructureClaimService::class);
        $proposalService = app(LocationProposalService::class);

        $noRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $proposal = $proposalService->propose($user, $city, $streetType, [
            'canonical_name' => 'خیابان مستقیم با ادعای ردشده',
        ], [$noRegion, $noNeighborhood]);

        $claimService->reject($noNeighborhood, $reviewer, 'ادعای ساختاری تأیید نشد');

        $this->expectException(DomainException::class);
        $proposalService->approve($proposal, $reviewer, 'نباید تأیید شود');
    }


    public function test_pending_region_exposes_neighborhood_structure_and_reanchors_claim_on_approval(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $regionType = $schema->types->firstWhere('key', 'urban_region');
        $schema->types()->updateExistingPivot($regionType->id, ['metadata' => json_encode(['crowdsourced_proposal_allowed' => true, 'structural_claim_types' => ['single_neighborhood','no_neighborhood']], JSON_UNESCAPED_UNICODE)]);
        $user = User::factory()->create();
        $reviewer = User::factory()->create(['is_admin' => true]);
        $proposal = app(LocationProposalService::class)->propose($user, $city, $regionType, ['canonical_name' => 'منطقه پیشنهادی']);

        $this->actingAs($user)->getJson('/location/proposals/'.$proposal->id.'/children')->assertOk()
            ->assertJsonFragment(['claim_type' => 'single_neighborhood'])->assertJsonFragment(['claim_type' => 'no_neighborhood']);
        $claimResponse = $this->actingAs($user)->postJson('/location/proposals/'.$proposal->id.'/structure-claims', ['claim_type' => 'no_neighborhood'])->assertCreated();
        $claimId = (int) $claimResponse->json('id');
        $default = $this->actingAs($user)->getJson('/location/proposals/'.$proposal->id.'/children')->assertOk()
            ->assertJsonPath('effective_allowed_types.0.key', 'neighborhood')
            ->assertJsonPath('registration_endpoint_allowed', false);
        $this->actingAs($user)->getJson('/location/proposals/'.$proposal->id.'/children?'.http_build_query([
            'location_structure_claim_ids' => [$claimId],
        ]))->assertOk()
            ->assertJsonPath('effective_allowed_types.0.key', 'street')
            ->assertJsonPath('registration_endpoint_allowed', true);

        $location = app(LocationProposalService::class)->approve($proposal, $reviewer, 'verified');
        $claim = LocationStructureClaim::query()->findOrFail((int) $claimResponse->json('id'));
        $this->assertSame($location->id, $claim->location_id);
        $this->assertNull($claim->location_proposal_id);
    }

    public function test_pending_parent_structural_claim_only_changes_its_path_when_explicitly_selected_and_can_create_the_effective_child(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        $schema = LocationFixture::iranSchema();
        $section = LocationFixture::createPath($schema, ['country','province','county','section'])->last();
        $cityType = $schema->types->firstWhere('key', 'city');
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $schema->types()->updateExistingPivot($cityType->id, ['metadata' => json_encode([
            'crowdsourced_proposal_allowed' => true,
            'structural_claim_types' => ['single_urban_region','no_urban_region'],
        ], JSON_UNESCAPED_UNICODE)]);
        $user = User::factory()->create();
        $cityProposal = app(LocationProposalService::class)->propose($user, $section, $cityType, ['canonical_name' => 'شهر پیشنهادی']);
        $claim = $this->actingAs($user)->postJson('/location/proposals/'.$cityProposal->id.'/structure-claims', [
            'claim_type' => 'no_urban_region',
        ])->assertCreated();
        $claimId = (int) $claim->json('id');

        $default = $this->actingAs($user)->getJson('/location/proposals/'.$cityProposal->id.'/children')->assertOk();
        $this->assertSame(['urban_region'], collect($default->json('effective_allowed_types'))->pluck('key')->all());

        $selected = $this->actingAs($user)->getJson('/location/proposals/'.$cityProposal->id.'/children?'.http_build_query([
            'location_structure_claim_ids' => [$claimId],
        ]))->assertOk();
        $this->assertSame(['neighborhood'], collect($selected->json('effective_allowed_types'))->pluck('key')->all());
        $selected->assertJsonPath('effective_allowed_types.0.proposal_allowed', true);

        $created = $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_proposal_id' => $cityProposal->id,
            'location_type_id' => $neighborhoodType->id,
            'canonical_name' => 'محله پیشنهادی مستقیم',
            'location_structure_claim_ids' => [$claimId],
        ])->assertCreated()->assertJsonPath('kind', 'proposal');

        $child = LocationProposal::query()->findOrFail((int) $created->json('id'));
        $this->assertSame([$claimId], data_get($child->metadata, 'structural_claim_ids'));
    }

    public function test_pending_city_and_village_expose_their_structural_choices(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $section = LocationFixture::createPath($schema, ['country','province','county','section'])->last();
        $rural = LocationFixture::createPath($schema, ['country','province','county','section','rural_district'])->last();

        foreach ([['parent' => $section, 'type' => 'city', 'claims' => ['single_urban_region','no_urban_region']], ['parent' => $rural, 'type' => 'village', 'claims' => ['single_neighborhood','no_neighborhood']]] as $case) {
            $type = $schema->types->firstWhere('key', $case['type']);
            $schema->types()->updateExistingPivot($type->id, ['metadata' => json_encode(['crowdsourced_proposal_allowed' => true, 'structural_claim_types' => $case['claims']], JSON_UNESCAPED_UNICODE)]);
            $proposal = app(LocationProposalService::class)->propose($user, $case['parent'], $type, ['canonical_name' => 'پیشنهاد '.$case['type']]);
            $response = $this->actingAs($user)->getJson('/location/proposals/'.$proposal->id.'/children')->assertOk();
            foreach ($case['claims'] as $claimType) $response->assertJsonFragment(['claim_type' => $claimType]);
        }
    }

}
