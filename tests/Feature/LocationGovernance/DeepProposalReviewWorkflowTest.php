<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Setting;
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
        Setting::singleton()->forceFill(['location_proposal_verification_threshold' => 1])->save();
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

    public function test_admin_queue_exposes_complete_mixed_canonical_and_pending_ancestry(): void
    {
        [$parentProposal, $childProposal, $anchor] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.location-governance.index'));

        $response->assertOk();
        $canonicalPath = [];
        for ($location = $anchor; $location !== null; $location = $location->parent()->first()) {
            $canonicalPath[] = $location;
        }
        foreach (array_reverse($canonicalPath) as $location) {
            $response->assertSee($location->canonical_name);
        }
        $response->assertSee($parentProposal->canonical_name);
        $response->assertSee($childProposal->canonical_name);
        $response->assertSee('در انتظار بررسی');
    }

    public function test_admin_queue_renders_dedicated_full_path_and_audited_rename_form_for_each_proposal(): void
    {
        [$parentProposal, $childProposal, $anchor] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.location-governance.index'));

        $response->assertOk()
            ->assertSee('data-proposal-path="'.$childProposal->id.'"', false)
            ->assertSee('data-proposal-rename-form="'.$childProposal->id.'"', false)
            ->assertSee('action="'.route('admin.location-governance.proposals.update', $childProposal).'"', false)
            ->assertSee('name="canonical_name"', false)
            ->assertSee('name="reason"', false);

        $html = $response->getContent();
        $pathStart = strpos($html, 'data-proposal-path="'.$childProposal->id.'"');
        $pathEnd = strpos($html, '</div>', $pathStart);
        $this->assertNotFalse($pathStart);
        $this->assertNotFalse($pathEnd);
        $pathHtml = substr($html, $pathStart, $pathEnd - $pathStart);

        $canonicalPath = [];
        for ($location = $anchor; $location !== null; $location = $location->parent()->first()) {
            $canonicalPath[] = $location->canonical_name;
        }
        foreach (array_reverse($canonicalPath) as $name) {
            $this->assertStringContainsString($name, $pathHtml);
        }
        $this->assertStringContainsString($parentProposal->canonical_name, $pathHtml);
        $this->assertStringContainsString($childProposal->canonical_name, $pathHtml);
    }

    public function test_admin_can_rename_open_proposal_and_change_is_audited_without_resolving_it(): void
    {
        [$proposal] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->putJson(
            route('admin.location-governance.proposals.update', $proposal),
            ['canonical_name' => 'خیابان اصلاح‌شده توسط مدیر', 'reason' => 'اصلاح نگارشی پیش از بررسی نهایی'],
        );

        $response->assertOk();
        $proposal->refresh();
        $this->assertSame('خیابان اصلاح‌شده توسط مدیر', $proposal->canonical_name);
        $this->assertSame(LocationProposalStatus::Pending, $proposal->status);
        $this->assertSame($admin->id, data_get($proposal->audit_log, '0.actor_user_id'));
        $this->assertSame('rename', data_get($proposal->audit_log, '0.action'));
    }

    public function test_hoda_treats_pending_proposal_parent_as_valid_structure_but_never_recommends_approval_before_parent_resolution(): void
    {
        [, $childProposal, $anchor] = $this->makeDeepProposalScenario();
        $verifier = User::factory()->create();
        $residence = app(ResidenceService::class);
        $residence->setInitialPrimaryResidence($verifier, $anchor, [
            'source' => 'deep-proposal-review-test',
        ]);
        $residence->setPendingResidenceIntent($verifier, $childProposal, [
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

    public function test_approving_intermediate_parent_reanchors_chain_without_prematurely_changing_official_residence(): void
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
        $this->assertSame($anchor->id, $current->location_id);
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
