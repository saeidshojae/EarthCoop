<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PendingResidenceDeepChainContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_deepest_pending_proposal_can_be_saved_while_official_residence_stays_on_nearest_canonical_anchor(): void
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $proposals = app(LocationProposalService::class);
        $residences = app(ResidenceService::class);

        $street = $proposals->propose($user, $anchor, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $alley = $proposals->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);
        $relationship = $residences->setInitialPrimaryResidence($user, $anchor, ['source' => 'test']);

        $intent = $residences->setPendingResidenceIntent($user, $alley, ['source' => 'test']);

        $this->assertSame($relationship->id, $intent->anchor_relationship_id);
        $this->assertSame($alley->id, $intent->location_proposal_id);
        $this->assertSame($anchor->id, $residences->currentPrimaryResidence($user)->location_id);
    }
}
