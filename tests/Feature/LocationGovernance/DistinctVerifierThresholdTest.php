<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class DistinctVerifierThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_cannot_inflate_verification_count_and_threshold_only_marks_ready_for_review(): void
    {
        config(['location-governance.location_proposal_verification_threshold' => 3]);

        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $proposer = User::factory()->create();
        $service = app(LocationProposalService::class);

        $proposal = $service->propose($proposer, $parent, $type, [
            'canonical_name' => 'مجتمع پیشنهادی',
        ]);

        $firstVerifier = User::factory()->create();
        $service->support($proposal, $firstVerifier, ['source' => 'manual']);
        $service->support($proposal->fresh(), $firstVerifier, ['source' => 'manual-updated']);

        $this->assertSame(1, $proposal->fresh()->evidence()->distinct('user_id')->count('user_id'));
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);

        $service->support($proposal->fresh(), User::factory()->create(), ['source' => 'manual']);
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);

        $service->support($proposal->fresh(), User::factory()->create(), ['source' => 'manual']);

        $proposal->refresh();
        $this->assertSame(3, $proposal->evidence()->distinct('user_id')->count('user_id'));
        $this->assertSame(LocationProposalStatus::ReadyForReview, $proposal->status);
        $this->assertNull($proposal->approved_at);
    }
}
