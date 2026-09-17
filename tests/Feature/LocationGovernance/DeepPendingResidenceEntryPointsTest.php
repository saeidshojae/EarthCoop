<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class DeepPendingResidenceEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
        ]);

        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);
    }

    public function test_registration_accepts_deepest_pending_proposal_and_keeps_nearest_canonical_anchor(): void
    {
        [$user, $anchor, $deepest] = $this->makeDeepProposalScenario();

        $response = $this->actingAs($user)->post(route('register.step3.process'), [
            'location_proposal_id' => $deepest->id,
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasNoErrors();

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()
            ->where('status', 'pending')
            ->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertSame($deepest->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
    }

    public function test_profile_accepts_deepest_pending_proposal_without_transferring_from_canonical_anchor(): void
    {
        [$user, $anchor, $deepest] = $this->makeDeepProposalScenario();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, ['source' => 'test']);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $deepest->id,
        ]);

        $response->assertSessionHasNoErrors();

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()
            ->where('status', 'pending')
            ->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame($deepest->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
    }

    public function test_admin_accepts_deepest_pending_proposal_and_preserves_audit_metadata(): void
    {
        [$target, $anchor, $deepest] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $anchor, ['source' => 'test']);
        $reason = 'ثبت جزئیات عمیق محل سکونت توسط مدیر';

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_proposal_id' => $deepest->id,
                'reason' => $reason,
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasNoErrors();

        $current = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $target->fresh()->pendingResidenceIntents()
            ->where('status', 'pending')
            ->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertSame($deepest->id, $intent->location_proposal_id);
        $this->assertSame($admin->id, data_get($intent->metadata, 'actor_user_id'));
        $this->assertSame($reason, data_get($intent->metadata, 'reason'));
    }

    private function makeDeepProposalScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی عمیق',
        ]);
        $alley = $service->proposeUnderProposal($user, $street, $alleyType, [
            'canonical_name' => 'کوچه پیشنهادی عمیق',
        ]);

        return [$user, $anchor, $alley];
    }
}
