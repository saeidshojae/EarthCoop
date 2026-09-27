<?php

namespace Tests\Feature\Api\V1;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

#[Group('mysql-location')]
class LocationGovernanceContractTest extends TestCase
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
    }

    public function test_native_profile_and_root_options_use_canonical_identity_and_match_web_runtime(): void
    {
        $user = $this->member();
        [$token, $deviceId] = $this->nativeSession($user);
        $schema = LocationFixture::iranSchema();
        $countryType = $schema->types->firstWhere('key', 'country');

        $country = Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran',
            'canonical_name' => 'Iran',
            'localized_names' => ['fa' => 'ایران'],
            'level' => 'country',
            'status' => 'active',
        ]);

        $profile = $this->freshBearer($token, $deviceId)->getJson('/api/v1/me');
        $profile->assertOk()->assertJsonPath('data.id', $user->id);

        $web = $this->getJson('/location/options/root?country=IR')->assertOk();
        $v1 = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/location/options/root?country=IR')
            ->assertOk();

        $this->assertSame($web->json(), $v1->json('data'));
        $v1->assertJsonPath('data.data.0.id', $country->id);
        $v1->assertJsonPath('data.data.0.identity', 'location:'.$country->id);
    }

    public function test_structural_city_region_and_village_edge_cases_match_canonical_web_runtime(): void
    {
        $user = $this->member();
        [$token, $deviceId] = $this->nativeSession($user);
        $schema = LocationFixture::iranSchema();
        $claims = app(LocationStructureClaimService::class);

        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $noRegion = $claims->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noCityNeighborhood = $claims->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $region = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region'])->last();
        $noRegionNeighborhood = $claims->findOrCreateOpenClaim($region, 'no_neighborhood', $user);

        $village = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'rural_district', 'village'])->last();
        $noVillageNeighborhood = $claims->findOrCreateOpenClaim($village, 'no_neighborhood', $user);

        foreach ([
            [$city, [$noRegion->id, $noCityNeighborhood->id]],
            [$region, [$noRegionNeighborhood->id]],
            [$village, [$noVillageNeighborhood->id]],
        ] as [$location, $claimIds]) {
            $query = http_build_query(['location_structure_claim_ids' => $claimIds]);
            $web = $this->getJson('/location/options/'.$location->id.'/children?'.$query)->assertOk();
            $v1 = $this->freshBearer($token, $deviceId)
                ->getJson('/api/v1/location/options/'.$location->id.'/children?'.$query)
                ->assertOk();

            $this->assertSame($web->json(), $v1->json('data'));
            $this->assertSame(['street'], collect($v1->json('data.effective_allowed_types'))->pluck('key')->all());
        }
    }

    public function test_pending_proposal_traversal_preserves_proposal_identity_and_matches_web_runtime(): void
    {
        $user = $this->member();
        [$token, $deviceId] = $this->nativeSession($user);
        $schema = LocationFixture::iranSchema();
        $neighborhood = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood']
        )->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $street = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی',
        ]);

        $web = $this->actingAs($user)->getJson('/location/proposals/'.$street->id.'/children')->assertOk();
        $v1 = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/location/proposals/'.$street->id.'/children')
            ->assertOk();

        $this->assertSame($web->json(), $v1->json('data'));
        $v1->assertJsonPath('data.structural_parent_proposal_id', $street->id);
    }

    public function test_native_residence_mutation_uses_canonical_structural_rules_and_summary(): void
    {
        $user = $this->member();
        [$token, $deviceId] = $this->nativeSession($user);
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $claims = app(LocationStructureClaimService::class);
        $noRegion = $claims->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $noNeighborhood = $claims->findOrCreateOpenClaim($city, 'no_neighborhood', $user);

        $this->freshBearer($token, $deviceId)
            ->putJson('/api/v1/location-governance/residence', [
                'location_id' => $city->id,
                'location_structure_claim_ids' => [$noRegion->id, $noNeighborhood->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.residence.location.identity', 'location:'.$city->id)
            ->assertJsonPath('data.residence.location.type_key', 'city');

        $summary = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/location-governance/me')
            ->assertOk();

        $summary->assertJsonPath('data.residence.location.id', $city->id);
        $summary->assertJsonPath('data.residence.location.identity', 'location:'.$city->id);
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'mobile-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
        ]);
    }

    private function nativeSession(User $user): array
    {
        $response = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => 'secret-password',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ])->assertCreated();

        return [$response->json('data.token'), $response->json('data.device.id')];
    }

    private function freshBearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
