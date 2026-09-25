<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\LocationProposal;
use App\Models\LocationScopedGroupRequest;
use App\Models\MembershipDimension;
use App\Models\Setting;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\Membership\PublicDimensionResolver;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class ReviewLifecycleCheckpoint3Test extends TestCase
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

        MembershipDimension::query()->firstOrCreate(
            ['key' => 'public'],
            [
                'name' => 'Public',
                'resolver_class' => PublicDimensionResolver::class,
                'enabled' => true,
            ],
        );
    }

    public function test_committed_deep_residence_supports_every_open_ancestor_idempotently_and_only_marks_ready_for_review(): void
    {
        Setting::singleton()->forceFill(['location_proposal_verification_threshold' => 2])->save();

        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $proposalService = app(LocationProposalService::class);

        $parent = $proposalService->propose(
            User::factory()->create(),
            $anchor,
            $streetType,
            ['canonical_name' => 'خیابان ایست سوم'],
        );
        $child = $proposalService->proposeUnderProposal(
            User::factory()->create(),
            $parent,
            $alleyType,
            ['canonical_name' => 'کوچه ایست سوم'],
        );

        $first = User::factory()->create();
        $this->commitPendingResidence($first, $anchor, $child);
        $this->commitPendingResidence($first, $anchor, $child);

        $this->assertSame(1, $parent->fresh()->evidence()->distinct()->count('user_id'));
        $this->assertSame(1, $child->fresh()->evidence()->distinct()->count('user_id'));
        $this->assertSame(LocationProposalStatus::Pending, $parent->fresh()->status);
        $this->assertSame(LocationProposalStatus::Pending, $child->fresh()->status);

        $this->commitPendingResidence(User::factory()->create(), $anchor, $child);

        $parent->refresh();
        $child->refresh();
        $this->assertSame(2, $parent->evidence()->distinct()->count('user_id'));
        $this->assertSame(2, $child->evidence()->distinct()->count('user_id'));
        $this->assertSame(LocationProposalStatus::ReadyForReview, $parent->status);
        $this->assertSame(LocationProposalStatus::ReadyForReview, $child->status);
        $this->assertNull($parent->approved_at);
        $this->assertNull($child->approved_at);

        foreach ([$parent, $child] as $proposal) {
            $this->assertTrue(collect($proposal->audit_log ?? [])->contains(
                fn (array $entry): bool =>
                    ($entry['to'] ?? null) === LocationProposalStatus::ReadyForReview->value
                    && ($entry['reason'] ?? null) === 'verification_threshold_reached'
            ));
        }
    }

    public function test_rejecting_selected_proposal_cancels_pending_intent_and_rejects_group_shell_without_moving_anchor(): void
    {
        $schema = LocationFixture::iranSchema();
        $region = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region',
        ])->last();
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $user = User::factory()->create();
        $reviewer = User::factory()->create(['is_admin' => true]);

        $proposal = app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $region,
            $neighborhoodType,
            ['canonical_name' => 'محله مردود ایست سوم'],
        );

        $residence = app(ResidenceService::class);
        $anchor = $residence->setInitialPrimaryResidence($user, $region, ['source' => 'checkpoint_3_reject']);
        $intent = $residence->setPendingResidenceIntent($user, $proposal, ['source' => 'checkpoint_3_reject']);
        $requests = app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $proposal);

        $this->assertNotEmpty($requests);
        $this->assertSame('pending', $intent->status);

        app(LocationProposalService::class)->reject($proposal, $reviewer, 'شواهد برای این محله کافی نیست');

        $proposal->refresh();
        $intent->refresh();
        $current = $residence->currentPrimaryResidence($user);

        $this->assertSame(LocationProposalStatus::Rejected, $proposal->status);
        $this->assertSame('cancelled', $intent->status);
        $this->assertSame('location_proposal_rejected', data_get($intent->metadata, 'cancellation_reason'));
        $this->assertSame($anchor->id, $current?->id);
        $this->assertSame($region->id, $current?->location_id);
        $this->assertSame(
            0,
            LocationScopedGroupRequest::query()
                ->where('requester_user_id', $user->id)
                ->where('location_proposal_id', $proposal->id)
                ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                ->count(),
        );
        $this->assertGreaterThan(
            0,
            LocationScopedGroupRequest::query()
                ->where('requester_user_id', $user->id)
                ->where('location_proposal_id', $proposal->id)
                ->where('status', 'rejected')
                ->count(),
        );
        $this->assertTrue(collect($proposal->audit_log ?? [])->contains(
            fn (array $entry): bool =>
                ($entry['to'] ?? null) === LocationProposalStatus::Rejected->value
                && ($entry['actor_user_id'] ?? null) === $reviewer->id
                && ($entry['reason'] ?? null) === 'شواهد برای این محله کافی نیست'
        ));
    }

    public function test_review_services_require_non_empty_human_reason(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $reviewer = User::factory()->create(['is_admin' => true]);

        $proposal = app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $parent,
            $type,
            ['canonical_name' => 'مجتمع دلیل اجباری'],
        );

        try {
            app(LocationProposalService::class)->requestMoreEvidence($proposal, $reviewer, '   ');
            $this->fail('Blank proposal review reason must fail.');
        } catch (DomainException) {
            $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);
        }

        $village = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district', 'village',
        ])->last();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim(
            $village,
            'no_neighborhood',
            User::factory()->create(),
        );

        try {
            app(LocationStructureClaimService::class)->approve($claim, $reviewer, '');
            $this->fail('Blank structural-claim review reason must fail.');
        } catch (DomainException) {
            $this->assertSame('pending', $claim->fresh()->status);
        }
    }

    public function test_admin_review_queue_shows_threshold_progress_and_dependent_pending_group_count(): void
    {
        Setting::singleton()->forceFill(['location_proposal_verification_threshold' => 2])->save();

        $schema = LocationFixture::iranSchema();
        $region = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region',
        ])->last();
        $type = $schema->types->firstWhere('key', 'neighborhood');
        $user = User::factory()->create();
        $proposal = app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $region,
            $type,
            ['canonical_name' => 'محله صف بازبینی'],
        );

        $this->commitPendingResidence($user, $region, $proposal);
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $proposal);

        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.location-governance.index'));

        $response->assertOk();
        $response->assertSee('حمایت: 1 از 2');
        $response->assertSee('گروه‌های وابسته در انتظار: 1');
        $response->assertSee('محله صف بازبینی');
    }

    private function commitPendingResidence(User $user, \App\Models\Location $anchor, LocationProposal $proposal): void
    {
        $residence = app(ResidenceService::class);
        if ($residence->currentPrimaryResidence($user) === null) {
            $residence->setInitialPrimaryResidence($user, $anchor, ['source' => 'checkpoint_3_support']);
        }

        $residence->setPendingResidenceIntent($user, $proposal, ['source' => 'checkpoint_3_support']);
    }
}
