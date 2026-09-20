<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class MyLocationGovernancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);
    }

    public function test_authenticated_user_has_my_location_governance_route_and_sidebar_entry(): void
    {
        $this->assertTrue(Route::has('location-governance.me'));

        $route = Route::getRoutes()->getByName('location-governance.me');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());

        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));
        $this->assertStringContainsString("route('location-governance.me')", $sidebar);
        $this->assertStringContainsString('مکان و حکمرانی من', $sidebar);
    }

    public function test_page_uses_official_governance_chain_and_separates_active_from_observer_memberships(): void
    {
        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $country = GovernanceArea::create([
            'key' => 'ir.country.my-governance',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'area_kind' => 'official',
            'canonical_name' => 'Iran Official Governance',
            'rank' => 1,
            'status' => 'active',
        ]);
        $baseArea->update([
            'parent_id' => $country->id,
            'canonical_name' => 'Sari Official Governance',
            'rank' => 10,
        ]);

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertViewIs('location-governance.my-location-governance');
        $response->assertViewHas('governanceAreas', function ($areas) use ($baseArea, $country): bool {
            $areas = collect($areas);

            return $areas->pluck('id')->values()->all() === [$baseArea->id, $country->id];
        });
        $response->assertViewHas('membershipsByDimension', function ($memberships): bool {
            $memberships = collect($memberships);

            foreach (['public', 'profession', 'specialty', 'age', 'gender'] as $dimension) {
                if (! $memberships->has($dimension)) {
                    return false;
                }

                $bucket = collect($memberships->get($dimension));
                if (! $bucket->has('active') || ! $bucket->has('observer')) {
                    return false;
                }
            }

            return true;
        });

        $response->assertSee('Sari Official Governance');
        $response->assertSee('Iran Official Governance');
        $response->assertSee('عضویت فعال');
        $response->assertSee('عضویت ناظر');
    }

    public function test_page_shows_current_approved_residence_without_treating_community_as_official_governance(): void
    {
        ['user' => $user, 'endpoint' => $endpoint] = MembershipFixture::canonicalUser();

        $community = GovernanceArea::create([
            'key' => 'community.test.my-location',
            'country_code' => 'IR',
            'governance_type' => 'community',
            'area_kind' => 'community',
            'canonical_name' => 'My Optional Community',
            'rank' => 100,
            'status' => 'active',
        ]);
        $community->locations()->attach($endpoint->id);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertViewHas('currentResidence', fn ($relationship): bool =>
            $relationship !== null && (int) $relationship->location_id === (int) $endpoint->id
        );
        $response->assertViewHas('governanceAreas', fn ($areas): bool =>
            ! collect($areas)->contains(fn ($area): bool => (int) $area->id === (int) $community->id)
        );
        $response->assertSee('محل سکونت تأییدشده');
        $response->assertSee('اجتماعات محلی');
    }

    public function test_page_uses_mobile_first_dashboard_contract_and_collapses_observer_lists(): void
    {
        ['user' => $user] = MembershipFixture::canonicalUser();

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('data-base-governance-summary', false);
        $response->assertSee('data-governance-chain', false);
        $response->assertSee('class="observer-memberships', false);
        $response->assertSee('<details', false);
        $response->assertSee('عضویت‌های ناظر');
        $response->assertSee('حوزه پایه حکمرانی');
        $response->assertDontSee('Governance Area');
    }

}
