<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class RegistrationPendingResidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
    }

    public function test_approved_endpoint_still_completes_registration_as_primary_residence(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('register.step3.process'), [
            'location_id' => $location->id,
            'location_proposal_id' => null,
        ]);

        $response->assertRedirect(route('home'));
        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($location->id, $relationship->location_id);
        $this->assertSame(0, PendingResidenceIntent::query()->where('user_id', $user->id)->count());
    }

    public function test_registration_rejects_approved_micro_location_below_governance_base(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ]);
        $street = $path->last();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'location_id' => $street->id,
                'location_proposal_id' => null,
            ]);

        $response->assertRedirect(route('register.step3'));
        $response->assertSessionHasErrors('location_id');
        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
    }

    public function test_registration_rejects_pending_micro_location_below_governance_base(): void
    {
        [$user, , $proposal] = $this->makeProposalScenario();

        $response = $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'location_proposal_id' => $proposal->id,
            ]);

        $response->assertRedirect(route('register.step3'));
        $response->assertSessionHasErrors('location_proposal_id');
        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertSame(0, PendingResidenceIntent::query()->where('user_id', $user->id)->count());
    }

    public function test_registration_rejects_zero_or_two_location_selections(): void
    {
        [$user, $anchor, $proposal] = $this->makeProposalScenario();

        $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [])
            ->assertRedirect(route('register.step3'))
            ->assertSessionHasErrors('location_id');

        $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'location_id' => $anchor->id,
                'location_proposal_id' => $proposal->id,
            ])
            ->assertRedirect(route('register.step3'))
            ->assertSessionHasErrors('location_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertSame(0, PendingResidenceIntent::query()->where('user_id', $user->id)->count());
    }

    public function test_terminal_proposal_cannot_complete_registration(): void
    {
        [$user, , $proposal] = $this->makeProposalScenario();
        $reviewer = User::factory()->create();
        app(LocationProposalService::class)->reject($proposal, $reviewer, 'not valid');

        $response = $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'location_proposal_id' => $proposal->id,
            ]);

        $response->assertRedirect(route('register.step3'));
        $response->assertSessionHasErrors('location_proposal_id');
        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
    }

    private function makeProposalScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $proposal = app(LocationProposalService::class)->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی ثبت نام',
        ]);

        $this->assertNotInstanceOf(Location::class, $proposal);

        return [$user, $anchor, $proposal];
    }
}
