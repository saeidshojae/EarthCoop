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
        $other=LocationFixture::createPath($schema,['country','province','county','section','city'], 'other-city')->last();
        $user=User::factory()->create();
        $claim=app(LocationStructureClaimService::class)->findOrCreateOpenClaim($other,'no_urban_region',$user);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$claim->id],
        ])->assertSessionHasErrors('location_structure_claim_ids');

        $this->assertSame(0,$user->fresh()->locationRelationships()->count());
        $this->assertSame(0,$claim->fresh()->evidence()->count());
    }
}
