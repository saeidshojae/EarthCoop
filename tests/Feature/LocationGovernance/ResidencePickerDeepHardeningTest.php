<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ResidencePickerDeepHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_fa_allowed_type_labels_are_localized_for_every_known_residence_type(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);
        $city = $path->last();

        $response = $this->getJson('/location/options/'.$city->id.'/children');

        $response->assertOk();
        $response->assertJsonPath('allowed_types.0.key', 'urban_region');
        $response->assertJsonPath('allowed_types.0.label', 'منطقه');
    }

    public function test_location_proposals_have_an_additive_nullable_proposal_parent_column(): void
    {
        $this->assertTrue(Schema::hasColumn('location_proposals', 'parent_location_proposal_id'));
    }

    public function test_open_proposal_can_parent_a_deeper_open_proposal_without_fabricating_a_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);

        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $alley = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);

        $this->assertInstanceOf(LocationProposal::class, $alley);
        $this->assertNull($alley->parent_location_id);
        $this->assertSame($street->id, $alley->parent_location_proposal_id);
        $this->assertSame($schema->id, $alley->location_schema_id);
        $this->assertSame('IR', $alley->country_code);
        $this->assertSame(LocationProposalStatus::Pending, $alley->status);
    }

    public function test_proposal_parent_reuses_same_open_child_and_rejects_invalid_type_relation(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $provinceType = $schema->types->firstWhere('key', 'province');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);

        $first = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه الف']);
        $second = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه الف']);
        $this->assertSame($first->id, $second->id);

        $this->expectException(DomainException::class);
        $service->proposeUnderProposal($user, $street, $provinceType, ['canonical_name' => 'استان نامعتبر']);
    }

    public function test_approving_parent_reanchors_direct_open_children_without_auto_approving_them(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $alley = $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);

        $approvedStreet = $service->approve($street, $reviewer, 'uat contract');
        $alley->refresh();

        $this->assertSame($approvedStreet->id, $alley->parent_location_id);
        $this->assertNull($alley->parent_location_proposal_id);
        $this->assertSame(LocationProposalStatus::Pending, $alley->status);
    }

    public function test_rejecting_parent_with_open_descendants_is_blocked(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);
        $service->proposeUnderProposal($user, $street, $alleyType, ['canonical_name' => 'کوچه پیشنهادی']);

        $this->expectException(DomainException::class);
        $service->reject($street, $reviewer, 'cannot orphan child');
    }

    public function test_proposal_children_endpoint_exposes_deeper_pending_options_with_persian_labels(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $street = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);

        $response = $this->actingAs($user)->getJson('/location/proposals/'.$street->id.'/children');

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('allowed_types.0.key', 'alley');
        $response->assertJsonPath('allowed_types.0.label', 'کوچه');
        $response->assertJsonPath('allowed_types.0.proposal_allowed', true);
    }

    public function test_store_accepts_exactly_one_parent_kind_for_deeper_proposal(): void
    {
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $street = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, ['canonical_name' => 'خیابان پیشنهادی']);

        $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_proposal_id' => $street->id,
            'location_type_id' => $alleyType->id,
            'canonical_name' => 'کوچه جدید',
            'localized_names' => ['fa' => 'کوچه جدید'],
        ])->assertCreated()->assertJsonPath('kind', 'proposal');

        $this->actingAs($user)->postJson('/locations/proposals', [
            'parent_location_id' => $neighborhood->id,
            'parent_location_proposal_id' => $street->id,
            'location_type_id' => $alleyType->id,
            'canonical_name' => 'والد مبهم',
        ])->assertUnprocessable();
    }
}
