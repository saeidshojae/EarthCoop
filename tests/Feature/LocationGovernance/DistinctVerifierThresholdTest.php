<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Setting;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class DistinctVerifierThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_cannot_inflate_verification_count_and_threshold_only_marks_ready_for_review(): void
    {
        Setting::singleton()->forceFill(['location_proposal_verification_threshold' => 3])->save();

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
        $this->commitResidenceSupport($firstVerifier, $parent, $proposal);
        $this->commitResidenceSupport($firstVerifier, $parent, $proposal);

        $this->assertSame(1, $proposal->fresh()->evidence()->distinct('user_id')->count('user_id'));
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);

        $this->commitResidenceSupport(User::factory()->create(), $parent, $proposal);
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);

        $this->commitResidenceSupport(User::factory()->create(), $parent, $proposal);

        $proposal->refresh();
        $this->assertSame(3, $proposal->evidence()->distinct('user_id')->count('user_id'));
        $this->assertSame(LocationProposalStatus::ReadyForReview, $proposal->status);
        $this->assertNull($proposal->approved_at);
    }

    private function commitResidenceSupport(User $user, \App\Models\Location $anchor, LocationProposal $proposal): void
    {
        $residence = app(ResidenceService::class);
        if ($residence->currentPrimaryResidence($user) === null) {
            $residence->setInitialPrimaryResidence($user, $anchor, ['source' => 'threshold-test']);
        }

        $residence->setPendingResidenceIntent($user, $proposal, ['source' => 'threshold-test']);
    }
}
