<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PendingResidenceIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_exact_residence_can_be_stored_without_replacing_approved_primary_residence(): void
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();

        $relationship = app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, [
            'source' => 'pending_residence_intent_contract',
        ]);

        $proposal = app(LocationProposalService::class)->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی آزمون',
        ]);

        $intent = PendingResidenceIntent::query()->create([
            'user_id' => $user->id,
            'anchor_relationship_id' => $relationship->id,
            'location_proposal_id' => $proposal->id,
            'status' => 'pending',
            'selected_at' => now(),
            'metadata' => ['source' => 'test'],
        ]);

        $currentResidence = $user->fresh()
            ->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($anchor->id, $currentResidence->location_id);
        $this->assertSame($proposal->id, $intent->locationProposal->id);
        $this->assertSame($relationship->id, $intent->anchorRelationship->id);
        $this->assertSame('pending', $intent->status);
        $this->assertSame(['source' => 'test'], $intent->metadata);
    }
}
