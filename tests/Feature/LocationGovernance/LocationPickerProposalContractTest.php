<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationPickerProposalContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_children_payload_exposes_active_locations_open_proposals_and_schema_allowed_types(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');

        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();

        $activeStreet = Location::factory()->create([
            'parent_id' => $neighborhood->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $streetType->id,
            'country_code' => 'IR',
            'name' => 'Approved Street',
            'canonical_name' => 'Approved Street',
            'localized_names' => ['fa' => 'خیابان تأییدشده'],
            'level' => 'street',
            'status' => 'active',
        ]);

        $openProposal = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی',
            'localized_names' => ['fa' => 'خیابان پیشنهادی'],
        ]);

        LocationProposal::query()->create([
            'proposer_user_id' => $user->id,
            'parent_location_id' => $neighborhood->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $streetType->id,
            'country_code' => 'IR',
            'canonical_name' => 'خیابان ردشده',
            'normalized_name' => 'خیابان ردشده',
            'status' => LocationProposalStatus::Rejected,
            'audit_log' => [],
        ]);

        $response = $this->getJson('/location/options/'.$neighborhood->id.'/children');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [[
                'id', 'identity', 'type_key', 'label', 'is_residence_endpoint', 'has_children', 'status',
            ]],
            'proposals' => [[
                'id', 'identity', 'type_key', 'label', 'status', 'selectable',
            ]],
            'allowed_types' => [[
                'id', 'key', 'label', 'proposal_allowed',
            ]],
        ]);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $activeStreet->id);
        $response->assertJsonPath('data.0.identity', 'location:'.$activeStreet->id);

        $response->assertJsonCount(1, 'proposals');
        $response->assertJsonPath('proposals.0.id', $openProposal->id);
        $response->assertJsonPath('proposals.0.identity', 'proposal:'.$openProposal->id);
        $response->assertJsonPath('proposals.0.status', LocationProposalStatus::Pending->value);
        $response->assertJsonPath('proposals.0.selectable', true);

        $response->assertJsonCount(1, 'allowed_types');
        $response->assertJsonPath('allowed_types.0.id', $streetType->id);
        $response->assertJsonPath('allowed_types.0.key', 'street');
        $response->assertJsonPath('allowed_types.0.proposal_allowed', true);
    }

    public function test_root_payload_keeps_the_same_three_collection_contract(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        $schema = LocationFixture::iranSchema();
        $countryType = $schema->types->firstWhere('key', 'country');

        Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran',
            'canonical_name' => 'Iran',
            'level' => 'country',
            'status' => 'active',
        ]);

        $response = $this->getJson('/location/options/root?country=IR');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'proposals', 'allowed_types']);
        $response->assertJsonPath('proposals', []);
        $response->assertJsonPath('allowed_types', []);
    }
}
