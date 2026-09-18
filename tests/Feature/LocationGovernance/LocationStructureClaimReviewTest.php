<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationStructureClaimReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_review_transitions_are_explicit_and_audited(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $proposer = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($city, 'single_urban_region', $proposer);

        $service->markNeedsEvidence($claim, $reviewer, 'مدرک بیشتری لازم است');
        $claim->refresh();
        $this->assertSame('needs_evidence', $claim->status);
        $this->assertSame($reviewer->id, $claim->reviewed_by_user_id);
        $this->assertSame('مدرک بیشتری لازم است', $claim->review_reason);
        $this->assertNull($claim->approved_at);

        $service->approve($claim, $reviewer, 'بررسی انسانی تکمیل شد');
        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame($reviewer->id, $claim->reviewed_by_user_id);
        $this->assertNotNull($claim->approved_at);
        $this->assertSame('بررسی انسانی تکمیل شد', $claim->review_reason);
        $this->assertSame('approved', collect($claim->audit_log)->last()['to']);
    }

    public function test_reject_is_terminal_and_terminal_claim_cannot_be_reviewed_again(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($village, 'no_neighborhood', User::factory()->create());
        $reviewer = User::factory()->create();

        $service->reject($claim, $reviewer, 'با شواهد رسمی سازگار نیست');
        $claim->refresh();

        $this->assertSame('rejected', $claim->status);
        $this->assertNull($claim->approved_at);
        $this->assertSame('rejected', collect($claim->audit_log)->last()['to']);

        $this->expectException(DomainException::class);
        $service->approve($claim, $reviewer, 'نباید مجاز باشد');
    }

    public function test_threshold_readiness_never_substitutes_for_human_approval(): void
    {
        config(['location-governance.location_structure_claim_verification_threshold' => 1]);
        $schema = LocationFixture::iranSchema();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($region, 'no_neighborhood', User::factory()->create());

        $service->recordCommittedSupport($claim, User::factory()->create(), ['source'=>'residence_commit']);
        $claim->refresh();

        $this->assertSame('ready_for_review', $claim->status);
        $this->assertNull($claim->approved_at);
        $this->assertNull($claim->reviewed_by_user_id);
    }
}
