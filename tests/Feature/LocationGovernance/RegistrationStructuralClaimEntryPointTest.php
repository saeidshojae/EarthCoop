<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class RegistrationStructuralClaimEntryPointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['location-governance.runtime_enabled'=>true,'location-governance.registration_enabled'=>true]);
    }

    public function test_registration_accepts_open_structural_claim_with_canonical_location_and_commits_support(): void
    {
        $schema=LocationFixture::iranSchema();
        $city=LocationFixture::createPath($schema,['country','province','county','section','city'])->last();
        $user=User::factory()->create();
        $claim=app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city,'no_urban_region',$user);

        $this->actingAs($user)->post(route('register.step3.process'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$claim->id],
        ])->assertRedirect(route('home'));

        $relationship=$user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame([$claim->id],$relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id',$user->id)->exists());
    }

    public function test_registration_rejects_structural_claim_belonging_to_another_location(): void
    {
        $schema=LocationFixture::iranSchema();
        $path=LocationFixture::createPath($schema,['country','province','county','section','city']);
        $city=$path->last();
        $other=LocationFixture::createPath($schema,['country','province','county','section','city'], ['کشور دوم','استان دوم','شهرستان دوم','بخش دوم','شهر دیگر'])->last();
        $user=User::factory()->create();
        $claim=app(LocationStructureClaimService::class)->findOrCreateOpenClaim($other,'no_urban_region',$user);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$claim->id],
        ])->assertSessionHasErrors('location_structure_claim_ids');

        $this->assertSame(0,$user->fresh()->locationRelationships()->count());
        $this->assertSame(0,$claim->fresh()->evidence()->count());
    }

    public function test_registration_commits_ancestor_structural_claim_when_residence_continues_to_micro_location(): void
    {
        $schema=LocationFixture::iranSchema();
        $path=LocationFixture::createPath($schema,['country','province','county','section','city']);
        $city=$path->last();
        $streetType=$schema->types->firstWhere('key','street');
        $street=\App\Models\Location::query()->create([
            'parent_id'=>$city->id,
            'location_schema_id'=>$schema->id,
            'location_type_id'=>$streetType->id,
            'country_code'=>'IR',
            'canonical_name'=>'خیابان مستقیم',
            'status'=>'active',
        ]);
        $user=User::factory()->create();
        $claim=app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city,'no_urban_region',$user);

        $this->actingAs($user)->post(route('register.step3.process'),[
            'location_id'=>$street->id,
            'location_structure_claim_ids'=>[$claim->id],
        ])->assertRedirect(route('home'));

        $relationship=$user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame($street->id,$relationship->location_id);
        $this->assertSame([$claim->id],$relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id',$user->id)->exists());
    }


    public function test_registration_commits_structural_claim_when_final_residence_detail_is_pending_proposal(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $proposal = \App\Models\LocationProposal::query()->create([
            'parent_location_id' => $city->id,
            'location_schema_id' => $schema->id,
            'country_code' => 'IR',
            'location_type_id' => $streetType->id,
            'canonical_name' => 'خیابان پیشنهادی مستقیم',
            'normalized_name' => 'خیابان پیشنهادی مستقیم',
            'localized_names' => ['fa' => 'خیابان پیشنهادی مستقیم'],
            'status' => \App\Enums\LocationGovernance\LocationProposalStatus::Pending,
            'proposer_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertRedirect(route('home'));

        $relationship = $user->fresh()->locationRelationships()->where('relationship_type', 'primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame($city->id, $relationship->location_id);
        $this->assertSame([$claim->id], $relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $user->id)->exists());
    }

}
