<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\LocationScopedGroupRequest;
use App\Models\ReferenceSettlementReview;
use App\Models\User;
use App\Services\LocationGovernance\ReferenceSettlementReviewService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReferenceSettlementReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function settlement(): ReferenceSettlement
    {
        return ReferenceSettlement::create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-201',
            'parent_external_id' => 'IR-1404-100',
            'source_code' => '201',
            'source_row_id' => 201,
            'name_fa' => 'آبادی نمونه صف بررسی',
            'search_name' => 'آبادی نمونه صف بررسی',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);
    }

    private function claim(ReferenceSettlement $settlement, User $user): ReferenceSettlementResidenceClaim
    {
        return ReferenceSettlementResidenceClaim::create([
            'reference_settlement_id' => $settlement->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function test_control_center_aggregates_unique_claimants_and_marks_threshold_as_priority_only(): void
    {
        config()->set('iran_settlement_catalog.claim_review_threshold', 3);
        $admin = User::factory()->create(['is_admin' => true]);
        $settlement = $this->settlement();
        foreach (range(1, 3) as $_) {
            $this->claim($settlement, User::factory()->create());
        }

        $response = $this->actingAs($admin)->get('/admin/location-governance');

        $response->assertOk()
            ->assertSee('درخواست‌های سکونت در آبادی‌های مرجع')
            ->assertSee('آبادی نمونه صف بررسی')
            ->assertSee('3 درخواست یکتا')
            ->assertSee('اولویت بررسی');
        $this->assertSame('unverified_settlement', $settlement->fresh()->classification);
        $this->assertFalse($settlement->fresh()->governance_authorized);
    }

    public function test_needs_evidence_is_audited_without_classification_or_governance_change(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settlement = $this->settlement();
        $claim = $this->claim($settlement, User::factory()->create());

        $response = $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            ['decision' => 'needs_evidence', 'reason' => 'مدرک رسمی جدید لازم است.'],
        );

        $response->assertOk()->assertJsonPath('decision', 'needs_evidence')
            ->assertJsonPath('governance_authorized', false);
        $this->assertSame('needs_review', $settlement->fresh()->classification);
        $this->assertSame('unverified', $settlement->fresh()->residential_eligibility);
        $this->assertSame('needs_evidence', $claim->fresh()->status);
        $review = ReferenceSettlementReview::query()->sole();
        $this->assertSame($admin->id, $review->reviewed_by_user_id);
        $this->assertSame('unverified_settlement', $review->snapshot['before']['classification']);
        $this->assertSame('needs_review', $review->snapshot['after']['classification']);
    }

    public function test_residential_evidence_requires_dated_source_and_never_grants_governance_or_primary_residence(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $claimant = User::factory()->create();
        $settlement = $this->settlement();
        $claim = $this->claim($settlement, $claimant);
        $governanceBefore = GovernanceArea::query()->count();

        $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            [
                'decision' => 'verified_residential_village',
                'reason' => 'هویت و وضعیت سکونتی با سند رسمی تطبیق شد.',
            ],
        )->assertUnprocessable();

        $response = $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            [
                'decision' => 'verified_residential_village',
                'reason' => 'هویت و وضعیت سکونتی با سند رسمی تطبیق شد.',
                'evidence_source' => 'مرجع رسمی نمونه',
                'evidence_date' => now()->subDay()->toDateString(),
                'evidence_reference' => 'DOC-1404-201',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('classification', 'verified_residential_village')
            ->assertJsonPath('governance_authorized', false);
        $settlement->refresh();
        $this->assertSame('verified', $settlement->residential_eligibility);
        $this->assertFalse($settlement->governance_authorized);
        $this->assertFalse($settlement->operational_promotion_allowed);
        $this->assertSame('residential_evidence_verified', $claim->fresh()->status);
        $this->assertSame(0, $claimant->locationRelationships()->count());
        $this->assertSame($governanceBefore, GovernanceArea::query()->count());
        $review = ReferenceSettlementReview::query()->sole();
        $this->assertSame('مرجع رسمی نمونه', $review->evidence_source);
        $this->assertSame('DOC-1404-201', $review->evidence_reference);
    }

    public function test_nonresidential_evidence_rejects_open_claims_but_preserves_catalog_identity(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settlement = $this->settlement();
        $claim = $this->claim($settlement, User::factory()->create());
        $groupRequest = LocationScopedGroupRequest::query()->create([
            'requester_user_id' => $claim->user_id,
            'reference_settlement_residence_claim_id' => $claim->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'status' => 'pending_location',
            'metadata' => ['source' => 'test'],
        ]);

        $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            [
                'decision' => 'verified_nonresidential_place',
                'reason' => 'مرجع رسمی محل را غیرمسکونی معرفی می‌کند.',
                'evidence_source' => 'مرجع رسمی نمونه',
                'evidence_date' => now()->subDay()->toDateString(),
                'evidence_reference' => 'NONRES-201',
            ],
        )->assertOk();

        $this->assertDatabaseHas('reference_settlements', [
            'id' => $settlement->id,
            'external_id' => 'IR-1404-201',
            'classification' => 'verified_nonresidential_place',
            'residential_eligibility' => 'ineligible',
            'governance_authorized' => 0,
        ]);
        $this->assertSame('rejected', $claim->fresh()->status);
        $this->assertSame('rejected', $groupRequest->fresh()->status);
        $this->assertNull($groupRequest->fresh()->group_id);
        $this->assertNull($groupRequest->fresh()->governance_area_id);
    }

    public function test_nonresidential_review_closes_pending_intent_and_group_shell_immediately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $settlement = $this->settlement();
        $claim = $this->claim($settlement, $user);

        $schema = \Tests\Support\LocationGovernance\LocationFixture::iranSchema();
        $anchor = \Tests\Support\LocationGovernance\LocationFixture::createPath($schema, ['country'])->last();
        $relationship = app(\App\Services\LocationGovernance\ResidenceService::class)
            ->setInitialPrimaryResidence($user, $anchor, ['source' => 'nonresidential-review-test']);
        $intent = \App\Models\PendingResidenceIntent::query()->create([
            'user_id' => $user->id,
            'anchor_relationship_id' => $relationship->id,
            'location_proposal_id' => null,
            'reference_settlement_residence_claim_id' => $claim->id,
            'status' => 'pending',
            'selected_at' => now(),
            'metadata' => ['source' => 'fixture'],
        ]);
        $groupRequest = \App\Models\LocationScopedGroupRequest::query()->create([
            'requester_user_id' => $user->id,
            'location_id' => null,
            'location_proposal_id' => null,
            'location_structure_claim_id' => null,
            'reference_settlement_residence_claim_id' => $claim->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'all',
            'status' => 'pending_location',
            'metadata' => ['source' => 'fixture'],
        ]);

        $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            [
                'decision' => 'verified_nonresidential_place',
                'reason' => 'مدرک رسمی نشان می‌دهد این مکان محل سکونت نیست.',
                'evidence_source' => 'مرجع رسمی نمونه',
                'evidence_date' => now()->subDay()->toDateString(),
                'evidence_reference' => 'NONRES-CLOSE-201',
            ],
        )->assertOk();

        $this->assertSame('rejected', $claim->fresh()->status);
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertSame(
            'reference_settlement_classified_nonresidential',
            $intent->fresh()->metadata['cancellation_reason'] ?? null,
        );
        $this->assertSame('rejected', $groupRequest->fresh()->status);
        $this->assertSame($anchor->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
    }

    public function test_verified_classification_cannot_be_silently_flipped_by_same_review_endpoint(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settlement = $this->settlement();
        $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            [
                'decision' => 'verified_nonresidential_place',
                'reason' => 'سند رسمی وضعیت غیرمسکونی را اثبات می‌کند.',
                'evidence_source' => 'مرجع رسمی نمونه',
                'evidence_date' => now()->subDay()->toDateString(),
                'evidence_reference' => 'NONRES-LOCK-201',
            ],
        )->assertOk();

        $this->actingAs($admin)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            [
                'decision' => 'verified_residential_village',
                'reason' => 'تلاش برای تغییر بدون جریان اصلاح مستقل.',
                'evidence_source' => 'مرجع دیگر',
                'evidence_date' => now()->subDay()->toDateString(),
                'evidence_reference' => 'OTHER-201',
            ],
        )->assertUnprocessable();

        $this->assertSame('verified_nonresidential_place', $settlement->fresh()->classification);
        $this->assertDatabaseCount('reference_settlement_reviews', 1);
    }

    public function test_stale_model_cannot_bypass_locked_verified_classification_guard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staleSettlement = $this->settlement();

        ReferenceSettlement::query()->whereKey($staleSettlement->id)->update([
            'classification' => 'verified_nonresidential_place',
            'residential_eligibility' => 'ineligible',
        ]);

        try {
            app(ReferenceSettlementReviewService::class)->review(
                $staleSettlement,
                $admin,
                'verified_residential_village',
                'مدل قدیمی نباید بتواند تصمیم جدید را روی طبقه‌بندی نهایی اعمال کند.',
                'مرجع رسمی نمونه',
                now()->subDay()->toDateString(),
                'STALE-GUARD-201',
            );
            $this->fail('Stale model bypassed locked review invariants.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('separate correction workflow', $exception->getMessage());
        }

        $this->assertSame('verified_nonresidential_place', ReferenceSettlement::query()->findOrFail($staleSettlement->id)->classification);
        $this->assertDatabaseCount('reference_settlement_reviews', 0);
    }

    public function test_non_admin_cannot_review_reference_settlement(): void
    {
        $settlement = $this->settlement();
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->postJson(
            "/admin/location-governance/reference-settlements/{$settlement->id}/review",
            ['decision' => 'needs_evidence', 'reason' => 'نباید قابل ثبت باشد.'],
        );

        $this->assertTrue(in_array($response->status(), [302, 403], true));
        $this->assertDatabaseCount('reference_settlement_reviews', 0);
        $this->assertSame('unverified_settlement', $settlement->fresh()->classification);
    }
}
