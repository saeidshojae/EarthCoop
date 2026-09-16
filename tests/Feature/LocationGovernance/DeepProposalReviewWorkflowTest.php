<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\NajmHoda\LocationGovernanceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class DeepProposalReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.location_proposal_verification_threshold' => 1,
        ]);
    }

    public function test_admin_queue_exposes_pending_parent_context_and_blocks_invalid_deep_proposal_actions(): void
    {
        [$parentProposal, $childProposal] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.location-governance.index'));

        $response->assertOk();
        $response->assertSee('والد پیشنهادی: '.$parentProposal->canonical_name);
        $response->assertSee('ابتدا پیشنهاد والد را تعیین تکلیف کنید');

        $response->assertDontSee(route('admin.location-governance.proposals.approve', $childProposal), false);
        $response->assertDontSee(route('admin.location-governance.proposals.merge', $childProposal), false);
        $response->assertDontSee(route('admin.location-governance.proposals.reject', $parentProposal), false);

        $response->assertSee(route('admin.location-governance.proposals.approve', $parentProposal), false);
        $response->assertSee(route('admin.location-governance.proposals.request-evidence', $childProposal), false);
    }

    public function test_hoda_treats_pending_proposal_parent_as_valid_structure_but_never_recommends_approval_before_parent_resolution(): void
    {
        [, $childProposal] = $this->makeDeepProposalScenario();
        $verifier = User::factory()->create();

        app(LocationProposalService::class)->support($childProposal, $verifier, [
            'source' => 'deep-proposal-review-test',
        ]);
        $childProposal->refresh();
        $this->assertSame(LocationProposalStatus::ReadyForReview, $childProposal->status);

        $review = app(LocationGovernanceReviewService::class)->review($childProposal);

        $this->assertSame('review', $review['recommendation']);
        $this->assertTrue($review['awaiting_parent_resolution']);
        $this->assertNotContains('missing_parent_location', $review['anomalies']);
        $this->assertNull($review['duplicate_candidate_id']);
        $this->assertSame(LocationProposalStatus::ReadyForReview, $childProposal->fresh()->status);
    }

    public function test_invalid_review_order_returns_validation_errors_instead_of_server_errors(): void
    {
        [$parentProposal, $childProposal] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);

        $approveChild = $this->actingAs($admin)->postJson(
            route('admin.location-governance.proposals.approve', $childProposal),
            ['reason' => 'بررسی زودهنگام فرزند قبل از تعیین تکلیف والد'],
        );

        $approveChild
            ->assertStatus(422)
            ->assertJsonValidationErrors('proposal');
        $this->assertSame(LocationProposalStatus::Pending, $childProposal->fresh()->status);

        $rejectParent = $this->actingAs($admin)->postJson(
            route('admin.location-governance.proposals.reject', $parentProposal),
            ['reason' => 'رد والد در حالی که فرزند باز دارد'],
        );

        $rejectParent
            ->assertStatus(422)
            ->assertJsonValidationErrors('proposal');
        $this->assertSame(LocationProposalStatus::Pending, $parentProposal->fresh()->status);
    }

    public function test_approving_intermediate_parent_refines_official_anchor_while_deepest_intent_stays_pending(): void
    {
        [$parentProposal, $childProposal, $anchor] = $this->makeDeepProposalScenario();
        $user = User::factory()->create();
        $reviewer = User::factory()->create(['is_admin' => true]);
        $residenceService = app(ResidenceService::class);
        $proposalService = app(LocationProposalService::class);

        $residenceService->setInitialPrimaryResidence($user, $anchor, [
            'source' => 'deep-anchor-refinement-test',
        ]);
        $intent = $residenceService->setPendingResidenceIntent($user, $childProposal, [
            'source' => 'deep-anchor-refinement-test',
        ]);

        $approvedParent = $proposalService->approve($parentProposal, $reviewer, 'تأیید مرحله میانی');
        $childProposal->refresh();
        $intent->refresh();
        $current = $residenceService->currentPrimaryResidence($user);

        $this->assertNotNull($current);
        $this->assertSame($approvedParent->id, $current->location_id);
        $this->assertSame('pending', $intent->status);
        $this->assertSame($childProposal->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
        $this->assertSame($approvedParent->id, $childProposal->nearestCanonicalParent()?->id);

        $approvedChild = $proposalService->approve($childProposal, $reviewer, 'تأیید مکان دقیق نهایی');
        $intent->refresh();
        $finalResidence = $residenceService->currentPrimaryResidence($user);

        $this->assertNotNull($finalResidence);
        $this->assertSame($approvedChild->id, $finalResidence->location_id);
        $this->assertSame('resolved', $intent->status);
        $this->assertSame($approvedChild->id, $intent->resolved_location_id);
    }

    public function test_profile_refresh_preserves_deepest_pending_selection_and_exposes_full_hydration_path(): void
    {
        [$parentProposal, $childProposal, $anchor] = $this->makeDeepProposalScenario();
        $user = User::factory()->create();
        $residenceService = app(ResidenceService::class);

        $residenceService->setInitialPrimaryResidence($user, $anchor, [
            'source' => 'deep-profile-hydration-test',
        ]);
        $residenceService->setPendingResidenceIntent($user, $childProposal, [
            'source' => 'deep-profile-hydration-test',
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('data-location-current-proposal-id="'.$childProposal->id.'"', false);
        $response->assertSee('name="location_id" value="" data-location-id', false);
        $response->assertSee('name="location_proposal_id" value="'.$childProposal->id.'"', false);
        $response->assertSeeInOrder([
            'location:'.$anchor->id,
            'proposal:'.$parentProposal->id,
            'proposal:'.$childProposal->id,
        ], false);

        $ux = file_get_contents(resource_path('js/registration-location-ux.js'));
        $this->assertIsString($ux);
        $this->assertStringContainsString('dataset.locationCurrentPath', $ux);
        $this->assertStringContainsString('dataset.locationCurrentProposalId', $ux);
        $this->assertStringContainsString('replayPersistedPath', $ux);
        $this->assertStringContainsString("dispatchEvent(new Event('change'", $ux);
    }

    /** @return array{0: \App\Models\LocationProposal, 1: \App\Models\LocationProposal, 2: \App\Models\Location} */
    private function makeDeepProposalScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $proposer = User::factory()->create();
        $service = app(LocationProposalService::class);

        $parentProposal = $service->propose($proposer, $anchor, $streetType, [
            'canonical_name' => 'خیابان والد در انتظار',
        ]);
        $childProposal = $service->proposeUnderProposal($proposer, $parentProposal, $alleyType, [
            'canonical_name' => 'کوچه فرزند در انتظار',
        ]);

        return [$parentProposal, $childProposal, $anchor];
    }
}
