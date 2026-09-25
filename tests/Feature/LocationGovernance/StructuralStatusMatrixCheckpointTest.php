<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\Membership\GroupCreationMode;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\GovernanceArea;
use App\Models\GroupCreationPolicy;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\MembershipDimension;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimPolicy;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\Membership\PublicDimensionResolver;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class StructuralStatusMatrixCheckpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        $dimension = MembershipDimension::query()->firstOrCreate(
            ['key' => 'public'],
            [
                'name' => 'Public',
                'resolver_class' => PublicDimensionResolver::class,
                'enabled' => true,
            ],
        );
        GroupCreationPolicy::query()->firstOrCreate(
            [
                'membership_dimension_id' => $dimension->id,
                'governance_area_id' => null,
            ],
            [
                'mode' => GroupCreationMode::Automatic,
                'enabled' => true,
            ],
        );
    }

    public function test_canonical_city_region_and_village_terminal_matrix_is_explicit_and_fail_closed(): void
    {
        $schema = LocationFixture::iranSchema();
        $service = app(LocationStructureClaimService::class);
        $tree = app(LocationTreeResolver::class);
        $reviewer = User::factory()->create();

        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $cityUser = User::factory()->create();
        $noRegion = $service->findOrCreateOpenClaim($city, 'no_urban_region', $cityUser);
        $noNeighborhood = $service->findOrCreateOpenClaim($city, 'no_neighborhood', $cityUser);

        $this->assertSame(
            [$noRegion->id],
            collect(data_get($noNeighborhood->metadata, 'depends_on_claim_ids', []))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        );
        $this->assertTrue($tree->registrationEndpointAllowed($city, [$noRegion, $noNeighborhood]));
        try {
            $service->approve($noNeighborhood, $reviewer, 'نباید پیش از پیش‌نیاز تأیید شود');
            $this->fail('Dependent city claim must not be approved before its prerequisite.');
        } catch (DomainException) {
            $this->assertSame('pending', $noNeighborhood->fresh()->status);
        }

        $service->approve($noRegion, $reviewer, 'نبود منطقه تأیید شد');
        $service->approve($noNeighborhood, $reviewer, 'نبود محله تأیید شد');
        $this->assertTrue($tree->registrationEndpointAllowed($city, [$noRegion->fresh(), $noNeighborhood->fresh()]));
        $this->getJson('/location/options/'.$city->id.'/children')
            ->assertOk()
            ->assertJsonPath('official_governance_base', true)
            ->assertJsonPath('registration_endpoint_allowed', true);

        foreach ([
            ['path' => ['country','province','county','section','city','urban_region'], 'type' => 'urban_region'],
            ['path' => ['country','province','county','section','rural_district','village'], 'type' => 'village'],
        ] as $case) {
            $base = LocationFixture::createPath($schema, $case['path'])->last();
            $user = User::factory()->create();
            $claim = $service->findOrCreateOpenClaim($base, 'no_neighborhood', $user);

            $this->assertTrue($tree->registrationEndpointAllowed($base, [$claim]));
            $service->approve($claim, $reviewer, 'پایان ساختاری تأیید شد');
            $this->assertTrue($tree->registrationEndpointAllowed($base, [$claim->fresh()]));
        }
    }

    public function test_rejecting_city_region_prerequisite_cascades_dependent_claim_and_pending_shell(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationStructureClaimService::class);

        $area = GovernanceArea::query()->create([
            'key' => 'matrix-city-'.$city->id,
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => $city->canonical_name,
            'rank' => 500,
            'status' => 'active',
        ]);
        $area->locations()->attach($city->id);

        $noRegion = $service->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $service->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $city,
            ['source' => 'checkpoint_2_matrix'],
            [$noRegion, $noNeighborhood],
        );

        $requests = app(PendingLocationGroupRequestService::class)
            ->openForUser($user)
            ->where('location_structure_claim_id', $noNeighborhood->id);
        $this->assertNotEmpty($requests);

        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $pendingNeighborhood = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $neighborhoodType,
            ['canonical_name' => 'محله مستقیم وابسته به نبود منطقه'],
            [$noRegion],
        );
        app(ResidenceService::class)->setPendingResidenceIntent(
            $user,
            $pendingNeighborhood,
            ['source' => 'checkpoint_2_prerequisite_rejection'],
        );
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $pendingNeighborhood);
        $this->assertSame(
            1,
            $user->pendingResidenceIntents()->where('status', 'pending')->count(),
        );

        $service->reject($noRegion, $reviewer, 'شهر در واقع منطقه‌بندی دارد');

        $this->assertSame('rejected', $noRegion->fresh()->status);
        $this->assertSame('rejected', $noNeighborhood->fresh()->status);
        $this->assertStringContainsString(
            'Prerequisite structural claim #'.$noRegion->id.' was rejected',
            (string) $noNeighborhood->fresh()->review_reason,
        );
        $this->assertSame(
            0,
            $user->locationScopedGroupRequests()
                ->where('location_structure_claim_id', $noNeighborhood->id)
                ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                ->count(),
        );
        $this->assertSame(
            0,
            $user->pendingResidenceIntents()->where('status', 'pending')->count(),
        );
        $this->assertSame(
            0,
            $user->locationScopedGroupRequests()
                ->where('location_proposal_id', $pendingNeighborhood->id)
                ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                ->count(),
        );
    }

    public function test_profile_and_admin_accept_complete_city_terminal_branch_with_same_shared_contract(): void
    {
        $schema = LocationFixture::iranSchema();

        $profileCity = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $profileUser = User::factory()->create();
        $claimService = app(LocationStructureClaimService::class);
        $profileNoRegion = $claimService->findOrCreateOpenClaim($profileCity, 'no_urban_region', $profileUser);
        $profileNoNeighborhood = $claimService->findOrCreateOpenClaim($profileCity, 'no_neighborhood', $profileUser);

        $this->actingAs($profileUser)->put(route('profile.update.address'), [
            'location_id' => $profileCity->id,
            'location_structure_claim_ids' => [$profileNoRegion->id, $profileNoNeighborhood->id],
        ])->assertSessionHasNoErrors();

        $profileRelationship = $profileUser->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $this->assertEqualsCanonicalizing(
            [$profileNoRegion->id, $profileNoNeighborhood->id],
            $profileRelationship->metadata['structural_claim_ids'],
        );

        $this->withoutMiddleware([AdminMiddleware::class, PermissionMiddleware::class]);
        $adminCity = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $target = User::factory()->create();
        $admin = User::factory()->create();
        $adminNoRegion = $claimService->findOrCreateOpenClaim($adminCity, 'no_urban_region', $target);
        $adminNoNeighborhood = $claimService->findOrCreateOpenClaim($adminCity, 'no_neighborhood', $target);

        $this->actingAs($admin)->put(route('admin.users.residence.update', $target), [
            'location_id' => $adminCity->id,
            'location_structure_claim_ids' => [$adminNoRegion->id, $adminNoNeighborhood->id],
            'reason' => 'ثبت شهر بدون منطقه و محله در ماتریس ایست دوم',
        ])->assertSessionHasNoErrors();

        $adminRelationship = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $this->assertEqualsCanonicalizing(
            [$adminNoRegion->id, $adminNoNeighborhood->id],
            $adminRelationship->metadata['structural_claim_ids'],
        );
        $this->assertTrue($adminNoNeighborhood->fresh()->evidence()->where('user_id', $target->id)->exists());
        $this->assertFalse($adminNoNeighborhood->fresh()->evidence()->where('user_id', $admin->id)->exists());
    }

    public function test_real_region_or_neighborhood_cannot_be_committed_with_contradictory_absence_claims(): void
    {
        $schema = LocationFixture::iranSchema();
        $claimService = app(LocationStructureClaimService::class);

        $urbanPath = LocationFixture::createPath(
            $schema,
            ['country','province','county','section','city','urban_region','neighborhood'],
        );
        $city = $urbanPath->first(fn (Location $location) => $location->type?->key === 'city');
        $region = $urbanPath->first(fn (Location $location) => $location->type?->key === 'urban_region');
        $neighborhood = $urbanPath->last();

        $user = User::factory()->create();
        $noRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        try {
            app(ResidenceService::class)->setInitialPrimaryResidence(
                $user,
                $region,
                ['source' => 'checkpoint_2_contradiction'],
                [$noRegion],
            );
            $this->fail('A real urban region must contradict no_urban_region.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('location_structure_claim_ids', $exception->errors());
        }

        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $region,
            ['source' => 'checkpoint_2_corrected_path'],
        );
        $this->assertSame(
            $region->id,
            $user->fresh()->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->sole()
                ->location_id,
        );

        $noNeighborhood = $claimService->findOrCreateOpenClaim($region, 'no_neighborhood', $user);
        try {
            app(ResidenceService::class)->transferPrimaryResidence(
                $user,
                $neighborhood,
                $user,
                'checkpoint_2_neighborhood_contradiction',
                false,
                [$noNeighborhood],
            );
            $this->fail('A real neighborhood must contradict no_neighborhood.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('location_structure_claim_ids', $exception->errors());
        }

        $proposalUser = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence(
            $proposalUser,
            $city,
            ['source' => 'checkpoint_2_pending_anchor'],
        );
        $proposalNoRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $proposalUser);
        $regionType = $schema->types->firstWhere('key', 'urban_region');

        try {
            app(LocationProposalService::class)->propose(
                $proposalUser,
                $city,
                $regionType,
                ['canonical_name' => 'منطقه واقعی پیشنهادی'],
                [$proposalNoRegion],
            );
            $this->fail('A real urban-region proposal must contradict no_urban_region.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }
    }

    public function test_pending_city_can_express_complete_no_region_no_neighborhood_branch_and_finish_registration(): void
    {
        $schema = LocationFixture::iranSchema();
        $section = LocationFixture::createPath($schema, ['country','province','county','section'])->last();
        $cityType = $schema->types->firstWhere('key', 'city');
        $cityPivot = \App\Models\LocationSchemaType::query()
            ->where('location_schema_id', $schema->id)
            ->where('location_type_id', $cityType->id)
            ->firstOrFail();
        $cityPivot->forceFill(['metadata' => array_merge($cityPivot->metadata ?? [], [
            'crowdsourced_proposal_allowed' => true,
        ])])->save();
        $user = User::factory()->create();

        $sectionArea = GovernanceArea::query()->create([
            'key' => 'checkpoint-2-section-'.$section->id,
            'country_code' => 'IR',
            'governance_type' => 'section',
            'area_kind' => 'official',
            'canonical_name' => $section->canonical_name,
            'rank' => 400,
            'status' => 'active',
        ]);
        $sectionArea->locations()->attach($section->id);

        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $section,
            $cityType,
            ['canonical_name' => 'شهر پیشنهادی بدون منطقه و محله'],
        );
        $this->assertInstanceOf(LocationProposal::class, $proposal);

        $noRegionResponse = $this->actingAs($user)
            ->postJson('/location/proposals/'.$proposal->id.'/structure-claims', [
                'claim_type' => 'no_urban_region',
            ])
            ->assertCreated();
        $noRegionId = (int) $noRegionResponse->json('id');

        $afterNoRegion = $this->actingAs($user)->getJson(
            '/location/proposals/'.$proposal->id.'/children?'.http_build_query([
                'location_structure_claim_ids' => [$noRegionId],
            ]),
        )->assertOk();

        $this->assertContains(
            'no_neighborhood',
            collect($afterNoRegion->json('structural_choices'))->pluck('claim_type')->all(),
        );
        $this->assertSame(
            ['neighborhood'],
            collect($afterNoRegion->json('effective_allowed_types'))->pluck('key')->all(),
        );

        $noNeighborhoodResponse = $this->actingAs($user)
            ->postJson('/location/proposals/'.$proposal->id.'/structure-claims', [
                'claim_type' => 'no_neighborhood',
            ])
            ->assertCreated();
        $noNeighborhoodId = (int) $noNeighborhoodResponse->json('id');

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$noNeighborhoodId],
        ])->assertSessionHasErrors('location_proposal_id');

        $terminal = $this->actingAs($user)->getJson(
            '/location/proposals/'.$proposal->id.'/children?'.http_build_query([
                'location_structure_claim_ids' => [$noRegionId, $noNeighborhoodId],
            ]),
        )->assertOk();

        $terminal->assertJsonPath('registration_endpoint_allowed', true);
        $this->assertSame(
            ['street'],
            collect($terminal->json('effective_allowed_types'))->pluck('key')->all(),
        );

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$noRegionId, $noNeighborhoodId],
        ])->assertRedirect(route('home'));

        $publicRequest = $user->locationScopedGroupRequests()
            ->where('location_proposal_id', $proposal->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->sole();
        $this->assertSame('pending_location', $publicRequest->status);

        $reviewer = User::factory()->create();
        $resolvedCity = app(LocationProposalService::class)->approve(
            $proposal,
            $reviewer,
            'تأیید شهر پیشنهادی در ماتریس ایست دوم',
        );

        $noRegion = LocationStructureClaim::query()->findOrFail($noRegionId);
        $noNeighborhood = LocationStructureClaim::query()->findOrFail($noNeighborhoodId);
        $this->assertSame($resolvedCity->id, $noRegion->location_id);
        $this->assertSame($resolvedCity->id, $noNeighborhood->location_id);
        $this->assertNull($noRegion->location_proposal_id);
        $this->assertNull($noNeighborhood->location_proposal_id);

        $publicRequest->refresh();
        $this->assertSame('pending_location', $publicRequest->status);
        $this->assertSame($noNeighborhood->id, (int) $publicRequest->location_structure_claim_id);

        $claimService = app(LocationStructureClaimService::class);
        $claimService->approve($noRegion, $reviewer, 'نبود منطقه تأیید شد');
        $publicRequest->refresh();
        $this->assertSame('pending_location', $publicRequest->status);

        $claimService->approve($noNeighborhood->fresh(), $reviewer, 'نبود محله تأیید شد');
        $publicRequest->refresh();
        $this->assertSame('materialized', $publicRequest->status);
        $this->assertNotNull($publicRequest->group_id);
        $this->assertSame(
            1,
            (int) $user->groups()->whereKey($publicRequest->group_id)
                ->wherePivot('status', 1)
                ->firstOrFail()
                ->pivot
                ->role,
        );
    }

    public function test_pending_city_dependent_claim_cannot_be_approved_before_prerequisite_and_is_cascaded_on_rejection(): void
    {
        $schema = LocationFixture::iranSchema();
        $section = LocationFixture::createPath($schema, ['country','province','county','section'])->last();
        $cityType = $schema->types->firstWhere('key', 'city');
        $cityPivot = \App\Models\LocationSchemaType::query()
            ->where('location_schema_id', $schema->id)
            ->where('location_type_id', $cityType->id)
            ->firstOrFail();
        $cityPivot->forceFill(['metadata' => array_merge($cityPivot->metadata ?? [], [
            'crowdsourced_proposal_allowed' => true,
        ])])->save();
        $user = User::factory()->create();
        $reviewer = User::factory()->create();

        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $section,
            $cityType,
            ['canonical_name' => 'شهر پیشنهادی ماتریس'],
        );

        $service = app(LocationStructureClaimService::class);
        $noRegion = $service->findOrCreateOpenClaimForProposal($proposal, 'no_urban_region', $user);
        $noNeighborhood = $service->findOrCreateOpenClaimForProposal($proposal, 'no_neighborhood', $user);

        try {
            $service->approve($noNeighborhood, $reviewer, 'تلاش زودهنگام');
            $this->fail('Dependent proposal claim must not be approved before its prerequisite.');
        } catch (DomainException) {
            $this->assertSame('pending', $noNeighborhood->fresh()->status);
        }

        $service->reject($noRegion, $reviewer, 'پیش‌نیاز رد شد');
        $this->assertSame('rejected', $noNeighborhood->fresh()->status);
    }

    public function test_structural_claims_reanchor_on_proposal_approval_and_merge_before_pending_resolution(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $regionType = $schema->types->firstWhere('key', 'urban_region');
        $claimService = app(LocationStructureClaimService::class);
        $proposalService = app(LocationProposalService::class);
        $reviewer = User::factory()->create();

        $approveUser = User::factory()->create();
        $approveProposal = $proposalService->propose(
            $approveUser,
            $city,
            $regionType,
            ['canonical_name' => 'منطقه جدید بدون محله'],
        );
        $approveClaim = $claimService->findOrCreateOpenClaimForProposal(
            $approveProposal,
            'no_neighborhood',
            $approveUser,
        );

        $approvedLocation = $proposalService->approve($approveProposal, $reviewer, 'تأیید منطقه');
        $this->assertSame($approvedLocation->id, $approveClaim->fresh()->location_id);
        $this->assertNull($approveClaim->fresh()->location_proposal_id);

        $mergeUser = User::factory()->create();
        $mergeProposal = $proposalService->propose(
            $mergeUser,
            $city,
            $regionType,
            ['canonical_name' => 'نام جایگزین منطقه موجود'],
        );
        $mergeClaim = $claimService->findOrCreateOpenClaimForProposal(
            $mergeProposal,
            'no_neighborhood',
            $mergeUser,
        );

        app(ResidenceService::class)->setInitialPrimaryResidence(
            $mergeUser,
            $city,
            ['source' => 'checkpoint_2_merge_anchor'],
        );

        app(ResidenceService::class)->setPendingResidenceIntent(
            $mergeUser,
            $mergeProposal,
            ['source' => 'checkpoint_2_merge'],
            [$mergeClaim],
        );
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($mergeUser, $mergeProposal);

        $existing = Location::query()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $regionType->id,
            'country_code' => 'IR',
            'name' => 'منطقه موجود',
            'canonical_name' => 'منطقه موجود',
            'level' => 'urban_region',
            'status' => 'active',
        ]);

        $proposalService->merge($mergeProposal, $existing, $reviewer, 'ادغام با منطقه موجود');

        $this->assertSame($existing->id, $mergeClaim->fresh()->location_id);
        $this->assertNull($mergeClaim->fresh()->location_proposal_id);

        $request = $mergeUser->locationScopedGroupRequests()
            ->where('location_structure_claim_id', $mergeClaim->id)
            ->where('dimension_key', 'public')
            ->firstOrFail();

        $this->assertSame('pending_location', $request->status);
        $this->assertNull($request->location_proposal_id);

        $claimService->approve($mergeClaim->fresh(), $reviewer, 'تأیید نبود محله پس از ادغام');
        $request->refresh();
        $this->assertSame('materialized', $request->status);
        $this->assertNotNull($request->governance_area_id);
        $this->assertNotNull($request->group_id);
        $this->assertSame(
            1,
            (int) $mergeUser->groups()
                ->whereKey($request->group_id)
                ->wherePivot('status', 1)
                ->firstOrFail()
                ->pivot
                ->role,
        );
    }

    public function test_merge_fails_closed_when_target_already_has_same_active_structural_claim(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $regionType = $schema->types->firstWhere('key', 'urban_region');
        $user = User::factory()->create();
        $reviewer = User::factory()->create();

        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $regionType,
            ['canonical_name' => 'منطقه ادغامی با ادعای ساختاری'],
        );
        $proposalClaim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaimForProposal($proposal, 'no_neighborhood', $user);

        $existing = Location::query()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $regionType->id,
            'country_code' => 'IR',
            'name' => 'منطقه مقصد',
            'canonical_name' => 'منطقه مقصد',
            'level' => 'urban_region',
            'status' => 'active',
        ]);
        app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($existing, 'no_neighborhood', User::factory()->create());

        try {
            app(LocationProposalService::class)->merge($proposal, $existing, $reviewer, 'نباید ادغام شود');
            $this->fail('Merge must fail closed when structural claim identity would be duplicated.');
        } catch (DomainException) {
            $this->assertSame($proposal->id, $proposalClaim->fresh()->location_proposal_id);
            $this->assertNull($proposalClaim->fresh()->location_id);
        }
    }

    public function test_residence_commit_rejects_contextual_claim_when_open_prerequisite_was_not_selected(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);

        $noRegion = $service->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $service->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        try {
            app(ResidenceService::class)->setInitialPrimaryResidence(
                $user,
                $city,
                ['source' => 'checkpoint_2_invalid_context'],
                [$noNeighborhood],
            );
            $this->fail('Open prerequisite must be explicitly selected with its dependent claim.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('location_structure_claim_ids', $exception->errors());
        }

        app(LocationStructureClaimService::class)->approve(
            $noRegion,
            User::factory()->create(),
            'پیش‌نیاز رسمی تأیید شد',
        );

        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $city,
            ['source' => 'checkpoint_2_approved_context'],
            [$noNeighborhood],
        );

        $this->assertSame(
            $city->id,
            $user->fresh()->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->sole()
                ->location_id,
        );
    }
}
