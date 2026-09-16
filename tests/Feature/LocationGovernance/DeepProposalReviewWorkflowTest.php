<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
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

    /** @return array{0: \App\Models\LocationProposal, 1: \App\Models\LocationProposal} */
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

        return [$parentProposal, $childProposal];
    }
}
