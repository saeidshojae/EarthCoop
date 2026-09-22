<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PendingResidenceAncestorResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_pending_parent_advances_anchor_without_resolving_deepest_intent(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $regionType = $schema->types->firstWhere('key', 'urban_region');
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $user = User::factory()->create();

        $anchor = UserLocationRelationship::create([
            'user_id' => $user->id,
            'location_id' => $city->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now()->subMinute(),
        ]);

        $proposals = app(LocationProposalService::class);
        $region = $proposals->propose($user, $city, $regionType, ['canonical_name' => 'منطقه در انتظار']);
        $neighborhood = $proposals->proposeUnderProposal($user, $region, $neighborhoodType, ['canonical_name' => 'محله در انتظار']);
        app(ResidenceService::class)->setPendingResidenceIntent($user, $neighborhood, ['source' => 'test']);

        $resolvedRegion = $proposals->approve($region, User::factory()->create(), 'تأیید منطقه');

        $intent = PendingResidenceIntent::query()->where('user_id', $user->id)->sole();
        $current = UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame('pending', $intent->status);
        $this->assertSame($resolvedRegion->id, $current->location_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
        $this->assertNotSame($anchor->id, $current->id);
        $this->assertSame($resolvedRegion->id, $neighborhood->fresh()->parent_location_id);
        $this->assertNull($neighborhood->fresh()->parent_location_proposal_id);

        $resolvedNeighborhood = $proposals->approve($neighborhood->fresh(), User::factory()->create(), 'تأیید محله');

        $intent->refresh();
        $current = UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame('resolved', $intent->status);
        $this->assertSame($resolvedNeighborhood->id, $intent->resolved_location_id);
        $this->assertSame($resolvedNeighborhood->id, $current->location_id);
    }
}
