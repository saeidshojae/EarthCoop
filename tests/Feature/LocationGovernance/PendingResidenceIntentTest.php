<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
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
        [$user, $anchor, $relationship, $proposal] = $this->makePendingScenario();

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

    public function test_residence_service_replaces_only_the_current_pending_intent(): void
    {
        [$user, $anchor, $relationship, $firstProposal, $schema] = $this->makePendingScenario();
        $service = app(ResidenceService::class);

        $first = $service->setPendingResidenceIntent($user, $firstProposal, ['source' => 'first']);

        $secondProposal = app(LocationProposalService::class)->propose(
            $user,
            $anchor,
            $schema->types->firstWhere('key', 'street'),
            ['canonical_name' => 'خیابان پیشنهادی دوم'],
        );
        $second = $service->setPendingResidenceIntent($user, $secondProposal, ['source' => 'second']);

        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->cancelled_at);
        $this->assertSame('pending', $second->status);
        $this->assertSame($relationship->id, $second->anchor_relationship_id);
        $this->assertSame(1, PendingResidenceIntent::query()->where('user_id', $user->id)->where('status', 'pending')->count());
    }

    public function test_committing_pending_residence_records_one_distinct_proposal_support_and_is_idempotent_for_the_same_user(): void
    {
        [$user, , , $proposal] = $this->makePendingScenario();
        $service = app(ResidenceService::class);

        $firstIntent = $service->setPendingResidenceIntent($user, $proposal, ['source' => 'first_commit']);

        $this->assertSame(1, $proposal->fresh()->evidence()->distinct()->count('user_id'));
        $evidence = $proposal->fresh()->evidence()->where('user_id', $user->id)->sole();
        $this->assertSame('residence_commit', data_get($evidence->evidence, 'source'));
        $this->assertSame($firstIntent->id, data_get($evidence->evidence, 'pending_residence_intent_id'));

        $secondIntent = $service->setPendingResidenceIntent($user, $proposal->fresh(), ['source' => 'repeat_commit']);

        $this->assertSame('cancelled', $firstIntent->fresh()->status);
        $this->assertSame('pending', $secondIntent->fresh()->status);
        $this->assertSame(1, $proposal->fresh()->evidence()->distinct()->count('user_id'));
        $updatedEvidence = $proposal->fresh()->evidence()->where('user_id', $user->id)->sole();
        $this->assertSame($secondIntent->id, data_get($updatedEvidence->evidence, 'pending_residence_intent_id'));
    }

    public function test_resolving_current_pending_intent_refines_residence_without_explicit_transfer(): void
    {
        [$user, $anchor, $relationship, $proposal, $schema] = $this->makePendingScenario();
        $service = app(ResidenceService::class);
        $intent = $service->setPendingResidenceIntent($user, $proposal, ['source' => 'resolution_test']);
        $resolved = $this->makeStreet($schema, $anchor, 'خیابان تأییدشده');

        $count = $service->resolvePendingResidenceIntents($proposal, $resolved);

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $count);
        $this->assertSame($resolved->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame('location_proposal_resolution', $current->change_reason);
        $this->assertNotNull($relationship->fresh()->ended_at);
        $this->assertSame('resolved', $intent->fresh()->status);
        $this->assertSame($resolved->id, $intent->fresh()->resolved_location_id);
    }

    public function test_merging_pending_proposal_into_existing_location_resolves_residence_without_explicit_transfer(): void
    {
        [$user, $anchor, $relationship, $proposal, $schema] = $this->makePendingScenario();
        $service = app(ResidenceService::class);
        $intent = $service->setPendingResidenceIntent($user, $proposal, ['source' => 'merge_resolution_test']);
        $existing = $this->makeStreet($schema, $anchor, 'خیابان موجود');
        $reviewer = User::factory()->create(['is_admin' => true]);

        app(LocationProposalService::class)->merge($proposal, $existing, $reviewer, 'ادغام با مکان موجود');

        $current = $service->currentPrimaryResidence($user);
        $this->assertNotNull($current);
        $this->assertSame($existing->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame('location_proposal_resolution', $current->change_reason);
        $this->assertNotNull($relationship->fresh()->ended_at);
        $this->assertSame('merged', $proposal->fresh()->status->value);
        $this->assertSame($existing->id, $proposal->fresh()->resolved_location_id);
        $this->assertSame('resolved', $intent->fresh()->status);
        $this->assertSame($existing->id, $intent->fresh()->resolved_location_id);
    }

    public function test_stale_pending_intent_cannot_move_user_after_real_residence_transfer(): void
    {
        [$user, $anchor, , $proposal, $schema] = $this->makePendingScenario();
        $service = app(ResidenceService::class);
        $intent = $service->setPendingResidenceIntent($user, $proposal, ['source' => 'stale_test']);
        $actualNewHome = $this->makeStreet($schema, $anchor, 'خیابان محل جدید واقعی');
        $proposalResolution = $this->makeStreet($schema, $anchor, 'خیابان حل پیشنهاد قدیمی');

        $service->transferPrimaryResidence($user, $actualNewHome, $user, 'real_move');
        $count = $service->resolvePendingResidenceIntents($proposal, $proposalResolution);

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame(0, $count);
        $this->assertSame($actualNewHome->id, $current->location_id);
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertNotNull($intent->fresh()->cancelled_at);
    }

    private function makePendingScenario(): array
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

        return [$user, $anchor, $relationship, $proposal, $schema];
    }

    private function makeStreet($schema, Location $parent, string $name): Location
    {
        $streetType = $schema->types->firstWhere('key', 'street');

        return Location::factory()->create([
            'parent_id' => $parent->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $streetType->id,
            'country_code' => 'IR',
            'name' => $name,
            'canonical_name' => $name,
            'level' => 'street',
            'status' => 'active',
        ]);
    }
}
