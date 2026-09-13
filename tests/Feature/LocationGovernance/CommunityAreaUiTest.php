<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Services\LocationGovernance\CommunityAreaService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class CommunityAreaUiTest extends TestCase
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

    public function test_eligible_approved_residence_exposes_policy_backed_community_create_action(): void
    {
        $this->assertTrue(Route::has('location-governance.community.store'));

        [$user, $complex] = $this->userAtComplexResidence();

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('data-community-create-action', false);
        $response->assertSee(route('location-governance.community.store', $complex), false);
        $response->assertSee('اجتماع محلی', false);
        $response->assertSee('انتخابات رسمی', false);
    }

    public function test_existing_community_is_shown_without_duplicate_create_action_and_post_is_idempotent(): void
    {
        [$user, $complex] = $this->userAtComplexResidence();

        $route = route('location-governance.community.store', $complex);
        $this->actingAs($user)->post($route)->assertRedirect();
        $this->actingAs($user)->post($route)->assertRedirect();

        $community = GovernanceArea::query()
            ->where('area_kind', 'community')
            ->whereHas('locations', fn ($query) => $query->whereKey($complex->id))
            ->sole();

        $this->assertSame(1, GovernanceArea::query()
            ->where('area_kind', 'community')
            ->whereHas('locations', fn ($query) => $query->whereKey($complex->id))
            ->count());

        $response = $this->actingAs($user)->get(route('location-governance.me'));
        $response->assertOk();
        $response->assertSee('data-community-area="'.$community->id.'"', false);
        $response->assertDontSee('data-community-create-action', false);
    }

    public function test_pending_or_ineligible_residence_never_gets_community_create_action(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);
        $neighborhood = $path->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $neighborhood, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی در انتظار بررسی',
        ]);
        app(ResidenceService::class)->setPendingResidenceIntent($user, $proposal, ['source' => 'test']);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('data-pending-residence-intent', false);
        $response->assertDontSee('data-community-create-action', false);
    }

    private function userAtComplexResidence(): array
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'complex',
        ]);
        $complex = $path->last();
        $user = User::factory()->create();

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $complex, ['source' => 'test']);

        return [$user, $complex];
    }
}
