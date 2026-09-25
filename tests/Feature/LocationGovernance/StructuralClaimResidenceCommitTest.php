<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class StructuralClaimResidenceCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_structural_claim_can_be_relied_on_by_committed_initial_residence_and_counts_support_once(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $relationship = app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $city,
            ['source'=>'registration'],
            [$claim]
        );

        $this->assertSame($city->id, $relationship->location_id);
        $this->assertSame([$claim->id], $relationship->fresh()->metadata['structural_claim_ids']);
        $this->assertSame(1, $claim->fresh()->evidence()->where('user_id', $user->id)->count());

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $city, ['source'=>'registration'], [$claim]);
        $this->assertSame(1, $claim->fresh()->evidence()->where('user_id', $user->id)->count());
    }

    public function test_terminal_structural_claim_cannot_be_used_for_residence_commit(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();
        $user = User::factory()->create();
        $service = app(LocationStructureClaimService::class);
        $claim = $service->findOrCreateOpenClaim($village, 'no_neighborhood', $user);
        $service->reject($claim, User::factory()->create(), 'invalid');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $village, ['source'=>'registration'], [$claim]);

        $this->assertSame(0, $claim->fresh()->evidence()->count());
    }

    public function test_existing_same_location_only_counts_new_claim_when_dependency_is_persisted(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $service = app(ResidenceService::class);
        $relationship = $service->setInitialPrimaryResidence($user, $city, ['source'=>'registration']);
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $same = $service->setInitialPrimaryResidence($user, $city, ['source'=>'profile'], [$claim]);

        $this->assertSame($relationship->id, $same->id);
        $this->assertSame([$claim->id], $same->fresh()->metadata['structural_claim_ids']);
        $this->assertSame(1, $claim->fresh()->evidence()->where('user_id', $user->id)->count());
    }

    public function test_residence_and_structural_support_roll_back_together_when_late_claim_support_fails(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $claimService = app(LocationStructureClaimService::class);
        $valid = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $terminal = $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $claimService->reject($terminal, User::factory()->create(), 'force terminal after hydration');

        try {
            app(ResidenceService::class)->setInitialPrimaryResidence(
                $user,
                $city,
                ['source'=>'registration'],
                [$valid, $terminal]
            );
            $this->fail('Expected structural claim validation failure.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('location_structure_claim_ids', $exception->errors());
        }

        $this->assertSame(0, $user->fresh()->locationRelationships()->where('relationship_type','primary_residence')->count());
        $this->assertSame(0, $valid->fresh()->evidence()->where('user_id', $user->id)->count());
    }

    public function test_admin_committing_residence_for_user_never_counts_admin_as_supporter(): void
    {
        $schema = LocationFixture::iranSchema();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($region, 'no_neighborhood', $user);

        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $region,
            ['source'=>'admin_edit','actor_user_id'=>$admin->id],
            [$claim]
        );

        $this->assertSame(1, $claim->fresh()->evidence()->count());
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertFalse($claim->fresh()->evidence()->where('user_id', $admin->id)->exists());
    }
}
