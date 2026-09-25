<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\Setting;
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
        Setting::singleton()->forceFill(['location_structure_claim_verification_threshold' => 3])->save();

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
    }    public function test_approved_claim_can_be_persisted_as_residence_dependency_without_new_support(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $user = User::factory()->create();
        $claim = LocationStructureClaim::query()->create([
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
            'status' => 'approved',
        ]);

        $relationship = app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $city,
            ['source' => 'approved-structural-dependency-test'],
            [$claim],
        );

        $this->assertSame([$claim->id], $relationship->fresh()->metadata['structural_claim_ids']);
        $this->assertSame(0, $claim->fresh()->evidence()->count());
    }

    public function test_admin_queue_lists_open_structural_claim_with_full_location_path_and_support_count(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim(
            $city,
            'no_urban_region',
            User::factory()->create(),
        );
        app(LocationStructureClaimService::class)->recordCommittedSupport(
            $claim,
            User::factory()->create(),
            ['source' => 'admin-queue-test'],
        );
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.location-governance.index'));

        $response->assertOk();
        $response->assertSee('ادعاهای ساختاری مکان');
        $response->assertSee('شهر بدون منطقه');
        $response->assertSee((string) $claim->fresh()->evidence()->distinct()->count('user_id'));

        $path = [];
        for ($cursor = $city; $cursor !== null; $cursor = $cursor->parent()->first()) {
            $path[] = $cursor;
        }
        foreach (array_reverse($path) as $location) {
            $response->assertSee($location->canonical_name);
        }
    }

    public function test_admin_can_review_structural_claim_but_support_threshold_does_not_auto_approve(): void
    {
        Setting::singleton()->forceFill(['location_structure_claim_verification_threshold' => 1])->save();

        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'rural_district', 'village'])->last();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($village, 'no_neighborhood', User::factory()->create());
        $service->recordCommittedSupport($claim, User::factory()->create(), ['source' => 'admin-review-test']);
        $this->assertSame('ready_for_review', $claim->fresh()->status);

        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->postJson(
            route('admin.location-governance.structure-claims.approve', $claim),
            ['reason' => 'بررسی انسانی و تأیید ساختار واقعی'],
        );

        $response->assertOk()->assertJsonPath('status', 'approved');
        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame($admin->id, $claim->reviewed_by_user_id);
        $this->assertNotNull($claim->approved_at);
    }

    public function test_admin_can_reject_structural_claim_with_audited_reason_via_http(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim(
            $village,
            'no_neighborhood',
            User::factory()->create(),
        );
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.location-governance.structure-claims.reject', $claim),
            ['reason' => 'شواهد رسمی این ادعا را تأیید نمی‌کند'],
        );

        $response->assertOk()->assertJsonPath('status', 'rejected');
        $claim->refresh();
        $this->assertSame('rejected', $claim->status);
        $this->assertSame($admin->id, $claim->reviewed_by_user_id);
        $this->assertSame('شواهد رسمی این ادعا را تأیید نمی‌کند', $claim->review_reason);
        $this->assertSame('rejected', data_get(collect($claim->audit_log)->last(), 'to'));
    }

    public function test_admin_can_request_more_evidence_for_structural_claim_via_http(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim(
            $city,
            'single_urban_region',
            User::factory()->create(),
        );
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.location-governance.structure-claims.request-evidence', $claim),
            ['reason' => 'مدرک رسمی تقسیمات شهری را پیوست کنید'],
        );

        $response->assertOk()->assertJsonPath('status', 'needs_evidence');
        $claim->refresh();
        $this->assertSame('needs_evidence', $claim->status);
        $this->assertSame($admin->id, $claim->reviewed_by_user_id);
        $this->assertSame('مدرک رسمی تقسیمات شهری را پیوست کنید', $claim->review_reason);
        $this->assertSame('needs_evidence', data_get(collect($claim->audit_log)->last(), 'to'));
    }

    public function test_admin_queue_describes_structural_claims_in_location_context(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $service = app(LocationStructureClaimService::class);

        $service->findOrCreateOpenClaim($city, 'no_urban_region', User::factory()->create());
        $service->findOrCreateOpenClaim($village, 'no_neighborhood', User::factory()->create());
        $service->findOrCreateOpenClaim($region, 'single_neighborhood', User::factory()->create());

        $admin = User::factory()->create(['is_admin' => true]);
        $page = $this->actingAs($admin)->get(route('admin.location-governance.index'));

        $page->assertOk()
            ->assertSee('شهر بدون منطقه')
            ->assertSee('روستای بدون محله')
            ->assertSee('منطقه تک‌محله');
    }

    public function test_admin_can_change_location_support_thresholds_persistently_from_location_governance_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->put(
            route('admin.location-governance.settings.update'),
            [
                'location_proposal_verification_threshold' => 7,
                'location_structure_claim_verification_threshold' => 12,
            ],
        );

        $response->assertRedirect();
        $settings = Setting::singleton()->fresh();
        $this->assertSame(7, (int) $settings->location_proposal_verification_threshold);
        $this->assertSame(12, (int) $settings->location_structure_claim_verification_threshold);

        $page = $this->actingAs($admin)->get(route('admin.location-governance.index'));
        $page->assertOk()
            ->assertSee('حد حمایت پیشنهاد مکان')
            ->assertSee('حد حمایت ادعای ساختاری')
            ->assertSee('value="7"', false)
            ->assertSee('value="12"', false);
    }

    public function test_authenticated_resident_can_create_or_reuse_an_allowed_structural_claim_via_http(): void
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
    public function test_conflicting_open_structural_claims_cannot_coexist_for_the_same_tier(): void
    {
        $schema = LocationFixture::iranSchema();
        $service = app(LocationStructureClaimService::class);
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();

        $service->findOrCreateOpenClaim($city, 'single_urban_region', $user);

        try {
            $service->findOrCreateOpenClaim($city, 'no_urban_region', User::factory()->create());
            $this->fail('Conflicting city structural claims must not coexist.');
        } catch (\DomainException $exception) {
            $this->assertSame('Conflicting structural claim already exists for this location tier.', $exception->getMessage());
        }

        $service->findOrCreateOpenClaim($region, 'no_neighborhood', $user);

        try {
            $service->findOrCreateOpenClaim($region, 'single_neighborhood', User::factory()->create());
            $this->fail('Conflicting neighborhood structural claims must not coexist.');
        } catch (\DomainException $exception) {
            $this->assertSame('Conflicting structural claim already exists for this location tier.', $exception->getMessage());
        }
    }

    public function test_approved_structural_claim_blocks_a_conflicting_new_claim(): void
    {
        $schema = LocationFixture::iranSchema();
        $service = app(LocationStructureClaimService::class);
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
            'status' => 'approved',
            'proposer_user_id' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Conflicting structural claim already exists for this location tier.');

        $service->findOrCreateOpenClaim($city, 'single_urban_region', User::factory()->create());
    }


}