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

    public function test_authenticated_user_can_support_a_proposal_and_same_user_remains_one_verifier(): void
    {
        config()->set('location-governance.location_proposal_verification_threshold', 2);

        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $proposer = User::factory()->create();
        $supporter = User::factory()->create();

        $this->actingAs($proposer)->postJson('/locations/proposals', [
            'parent_location_id' => $parent->id,
            'location_type_id' => $type->id,
            'canonical_name' => 'مجتمع نیازمند تایید',
        ])->assertCreated();

        $proposal = LocationProposal::query()->sole();

        $this->actingAs($supporter)->postJson("/locations/proposals/{$proposal->id}/support", [
            'evidence' => ['note' => 'نشانی را تایید می‌کنم.'],
        ])->assertOk()->assertJsonPath('distinct_verifiers', 1);

        $this->actingAs($supporter)->postJson("/locations/proposals/{$proposal->id}/support", [
            'evidence' => ['note' => 'تایید دوباره همان کاربر'],
        ])->assertOk()->assertJsonPath('distinct_verifiers', 1);

        $this->actingAs(User::factory()->create())->postJson("/locations/proposals/{$proposal->id}/support", [
            'evidence' => ['note' => 'تایید کاربر دوم'],
        ])->assertOk()
            ->assertJsonPath('distinct_verifiers', 2)
            ->assertJsonPath('status', LocationProposalStatus::ReadyForReview->value);
    }

    public function test_location_proposal_routes_require_authentication(): void
    {
        $this->postJson('/locations/proposals', [])->assertUnauthorized();
    }
}
