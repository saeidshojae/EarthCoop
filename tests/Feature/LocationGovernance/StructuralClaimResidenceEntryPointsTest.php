<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class StructuralClaimResidenceEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['location-governance.runtime_enabled'=>true,'location-governance.registration_enabled'=>true,'location-governance.groups_enabled'=>false]);
    }

    public function test_profile_initial_residence_commits_open_structural_claim_support(): void
    {
        $schema=LocationFixture::iranSchema();
        $city=LocationFixture::createPath($schema,['country','province','county','section','city'])->last();
        $user=User::factory()->create();
        $claim=app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city,'no_urban_region',$user);

        $this->actingAs($user)->put(route('profile.update.address'),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$claim->id],
        ])->assertSessionHasNoErrors();

        $relationship=$user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame([$claim->id],$relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id',$user->id)->exists());
    }

    public function test_profile_can_commit_region_without_neighborhood_as_residence_with_explicit_structural_claim(): void
    {
        $schema = LocationFixture::iranSchema();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($region, 'no_neighborhood', $user);

        $this->actingAs($user)->put(route('profile.update.address'), [
            'location_id' => $region->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertSessionHasNoErrors();

        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($region->id, $relationship->location_id);
        $this->assertSame([$claim->id], $relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $user->id)->exists());
    }

    public function test_admin_initial_residence_commits_support_for_resident_not_admin(): void
    {
        $this->withoutMiddleware([AdminMiddleware::class,PermissionMiddleware::class]);
        $schema=LocationFixture::iranSchema();
        $city=LocationFixture::createPath($schema,['country','province','county','section','city'])->last();
        $target=User::factory()->create();
        $admin=User::factory()->create();
        $claim=app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city,'no_urban_region',$target);

        $this->actingAs($admin)->put(route('admin.users.residence.update',$target),[
            'location_id'=>$city->id,
            'location_structure_claim_ids'=>[$claim->id],
            'reason'=>'ثبت ساختار واقعی محل سکونت',
        ])->assertSessionHasNoErrors();

        $relationship=$target->fresh()->locationRelationships()->where('relationship_type','primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame([$claim->id],$relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id',$target->id)->exists());
        $this->assertFalse($claim->fresh()->evidence()->where('user_id',$admin->id)->exists());
    }

    public function test_admin_can_commit_region_without_neighborhood_for_resident_with_explicit_structural_claim(): void
    {
        $this->withoutMiddleware([AdminMiddleware::class,PermissionMiddleware::class]);
        $schema = LocationFixture::iranSchema();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $target = User::factory()->create();
        $admin = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($region, 'no_neighborhood', $target);

        $this->actingAs($admin)->put(route('admin.users.residence.update', $target), [
            'location_id' => $region->id,
            'location_structure_claim_ids' => [$claim->id],
            'reason' => 'ثبت منطقه بدون محله کاربر',
        ])->assertSessionHasNoErrors();

        $relationship = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($region->id, $relationship->location_id);
        $this->assertSame([$claim->id], $relationship->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $target->id)->exists());
        $this->assertFalse($claim->fresh()->evidence()->where('user_id', $admin->id)->exists());
    }
}
