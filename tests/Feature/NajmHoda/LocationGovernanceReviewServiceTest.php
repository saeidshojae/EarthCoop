<?php

namespace Tests\Feature\NajmHoda;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\NajmHoda\LocationGovernanceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationGovernanceReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hoda_can_summarize_and_recommend_without_mutating_proposal_state(): void
    {
        [$proposal] = $this->makeProposal('مجتمع بررسی هدا');

        $review = app(LocationGovernanceReviewService::class)->review($proposal);

        $this->assertSame($proposal->id, $review['proposal_id']);
        $this->assertSame(LocationProposalStatus::Pending->value, $review['status']);
        $this->assertContains($review['recommendation'], ['approve', 'reject', 'merge', 'needs_evidence', 'review']);
        $this->assertArrayHasKey('rationale', $review);
        $this->assertArrayHasKey('duplicate_candidate_id', $review);
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);
        $this->assertNull($proposal->fresh()->reviewed_by_user_id);
    }

    public function test_hoda_flags_likely_duplicate_as_merge_recommendation_without_merging_it(): void
    {
        [$proposal, $parent, $type, $schema] = $this->makeProposal('مجتمع ارغوان پیشنهادی', true);
        $existing = Location::factory()->create([
            'parent_id' => $parent->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $type->id,
            'country_code' => 'IR',
            'name' => 'مجتمع ارغوان پیشنهادی',
            'canonical_name' => 'مجتمع ارغوان پیشنهادی',
            'level' => 'complex',
            'status' => 'active',
        ]);

        $review = app(LocationGovernanceReviewService::class)->review($proposal->fresh());

        $this->assertSame('merge', $review['recommendation']);
        $this->assertSame($existing->id, $review['duplicate_candidate_id']);
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);
        $this->assertNull($proposal->fresh()->resolved_location_id);
    }

    private function makeProposal(string $name, bool $manualProposal = false): array
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');

        if ($manualProposal) {
            $proposal = \App\Models\LocationProposal::query()->create([
                'proposer_user_id' => User::factory()->create()->id,
                'parent_location_id' => $parent->id,
                'location_schema_id' => $schema->id,
                'location_type_id' => $type->id,
                'country_code' => 'IR',
                'canonical_name' => $name,
                'normalized_name' => mb_strtolower(trim($name)),
                'status' => LocationProposalStatus::Pending,
                'audit_log' => [],
            ]);
        } else {
            $proposal = app(LocationProposalService::class)->propose(
                User::factory()->create(),
                $parent,
                $type,
                ['canonical_name' => $name],
            );
        }

        return [$proposal, $parent, $type, $schema];
    }
}
