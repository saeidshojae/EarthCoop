<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationProposalHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_a_location_proposal_and_reuse_existing_pending_candidate(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_id' => $parent->id,
            'location_type_id' => $type->id,
            'canonical_name' => 'مجتمع ارغوان',
            'localized_names' => ['fa' => 'مجتمع ارغوان'],
        ]);

        $first->assertCreated()
            ->assertJsonPath('kind', 'proposal')
            ->assertJsonPath('status', LocationProposalStatus::Pending->value);

        $proposal = LocationProposal::query()->sole();

        $second = $this->actingAs(User::factory()->create())->postJson('/locations/proposals', [
            'parent_location_id' => $parent->id,
            'location_type_id' => $type->id,
            'canonical_name' => '  مجتمع   ارغوان  ',
        ]);

        $second->assertOk()
            ->assertJsonPath('kind', 'proposal')
            ->assertJsonPath('id', $proposal->id);
        $this->assertSame(1, LocationProposal::query()->count());
    }

    public function test_submit_reuses_existing_canonical_location_without_creating_a_proposal(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $existing = Location::factory()->create([
            'parent_id' => $parent->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $type->id,
            'country_code' => 'IR',
            'name' => 'مجتمع موجود',
            'canonical_name' => 'مجتمع موجود',
            'level' => 'complex',
            'status' => 'active',
        ]);

        $response = $this->actingAs(User::factory()->create())->postJson('/locations/proposals', [
            'parent_location_id' => $parent->id,
            'location_type_id' => $type->id,
            'canonical_name' => ' مجتمع   موجود ',
        ]);

        $response->assertOk()
            ->assertJsonPath('kind', 'location')
            ->assertJsonPath('id', $existing->id);
        $this->assertSame(0, LocationProposal::query()->count());
    }

    public function test_manual_support_endpoint_is_not_exposed_and_proposal_creation_alone_is_not_support(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $proposer = User::factory()->create();

        $this->actingAs($proposer)->postJson('/locations/proposals', [
            'parent_location_id' => $parent->id,
            'location_type_id' => $type->id,
            'canonical_name' => 'مجتمع نیازمند تأیید',
        ])->assertCreated();

        $proposal = LocationProposal::query()->sole();
        $this->assertSame(0, $proposal->evidence()->count());

        $this->actingAs(User::factory()->create())
            ->postJson("/locations/proposals/{$proposal->id}/support", [
                'evidence' => ['note' => 'حمایت بدون ثبت محل سکونت'],
            ])
            ->assertNotFound();

        $this->assertSame(0, $proposal->fresh()->evidence()->count());
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);
    }

    public function test_inactive_parent_cannot_receive_a_new_location_proposal(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $parent->update(['status' => 'inactive']);
        $type = $schema->types->firstWhere('key', 'complex');

        $response = $this->actingAs(User::factory()->create())->postJson('/locations/proposals', [
            'parent_location_id' => $parent->id,
            'location_type_id' => $type->id,
            'canonical_name' => 'مجتمع زیر والد غیرفعال',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('parent_location_id');
        $this->assertSame(0, LocationProposal::query()->count());
    }

    public function test_location_proposal_routes_require_authentication(): void
    {
        $this->postJson('/locations/proposals', [])->assertUnauthorized();
    }
}
