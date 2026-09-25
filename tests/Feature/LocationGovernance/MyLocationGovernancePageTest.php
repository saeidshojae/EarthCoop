<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\LocationGovernance\LocationFixture;
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

    public function test_sidebar_group_badge_uses_canonical_active_memberships_during_cutover(): void
    {
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        $this->assertStringContainsString('CanonicalGroupMembershipReconciler::class', $sidebar);
        $this->assertStringContainsString("wherePivot('status', 1)", $sidebar);
        $this->assertStringContainsString("config('location-governance.groups_enabled'", $sidebar);
        $this->assertStringNotContainsString('$groups = auth()->user()->groups;', $sidebar);
    }

    public function test_official_governance_name_uses_active_locale_and_language_fallback(): void
    {
        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();
        $area->forceFill([
            'canonical_name' => 'English Governance Area',
            'localized_names' => ['fa' => 'حوزه حکمرانی فارسی', 'en' => 'English Governance Area'],
        ])->save();

        app()->setLocale('fa-IR');
        $this->actingAs($user)->get(route('location-governance.me'))
            ->assertOk()->assertSee('حوزه حکمرانی فارسی');

        app()->setLocale('en');
        $this->actingAs($user)->get(route('location-governance.me'))
            ->assertOk()->assertSee('English Governance Area');
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
        $response->assertSee('عضویت‌های ناظر');
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
        $response->assertSee('محل سکونت');
        $response->assertSee('تأییدشده');
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
        $response->assertSee('class="governance-disclosure"', false);
        $response->assertSee('class="membership-summary-row"', false);
        $response->assertSee('<details', false);
        $response->assertSee('مشاهده زنجیره');
        $response->assertSee('عضویت فعال');
        $response->assertSee('عضویت ناظر');
        $response->assertSee('حوزه پایه حکمرانی');
        $response->assertDontSee('Governance Area');
    }


    public function test_page_presents_pending_no_neighborhood_base_separately_from_formal_governance_chain(): void
    {
        ['user' => $user, 'area' => $cityArea, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();
        $regionType = $schema->types()->where('key', 'urban_region')->firstOrFail();

        $region = Location::query()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $regionType->id,
            'country_code' => 'IR',
            'canonical_name' => 'منطقه پایه در انتظار ساختار',
            'localized_names' => ['fa' => 'منطقه پایه در انتظار ساختار'],
            'status' => 'active',
        ]);
        $regionArea = GovernanceArea::query()->create([
            'parent_id' => $cityArea->id,
            'key' => 'ir.pending-structural-base-page',
            'country_code' => 'IR',
            'governance_type' => 'urban_region',
            'area_kind' => 'official',
            'canonical_name' => 'Pending Structural Region',
            'rank' => 20,
            'status' => 'active',
        ]);
        $regionArea->locations()->attach($region->id);

        $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->update(['ended_at' => now()->subSecond()]);

        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($region, 'no_neighborhood', $user);
        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $region,
            ['source' => 'my_location_pending_structural_base'],
            [$claim],
        );

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertViewHas('governanceAreas', fn ($areas): bool =>
            ! collect($areas)->contains('id', $regionArea->id)
            && collect($areas)->contains('id', $cityArea->id)
        );
        $response->assertViewHas('pendingGovernanceStructuralClaims', fn ($claims): bool =>
            collect($claims)->contains('id', $claim->id)
        );
        $response->assertSee('data-pending-structural-base', false);
        $response->assertSee('منطقه پایه در انتظار ساختار');
        $response->assertSee('حوزه پایه در انتظار تأیید ساختار');
    }

    public function test_page_defaults_to_official_tab_and_guides_user_without_micro_location(): void
    {
        ['user' => $user] = MembershipFixture::canonicalUser();

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('id="official-tab"', false);
        $response->assertSee('nav-link active', false);
        $response->assertSee('اجتماعات محلی');
        $response->assertSee('data-community-location-guide', false);
        $response->assertSee('نشانی محلی شما هنوز تکمیل نشده است');
        $response->assertSee('تکمیل نشانی محلی');
    }


    public function test_page_uses_progressive_disclosure_without_changing_membership_totals(): void
    {
        ['user' => $user] = MembershipFixture::canonicalUser();

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('محل سکونت من');
        $response->assertSee('حکمرانی رسمی من');
        $response->assertSee('governance-overview', false);
        $response->assertSee('membership-summary-row', false);

        $memberships = $user->groups()
            ->whereNotNull('governance_area_id')
            ->wherePivot('status', 1)
            ->get();

        $response->assertSee((string) $memberships->count());
    }


    public function test_local_communities_tab_lists_every_micro_location_in_residence_path_independently(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'alley', 'complex', 'building'],
            ['ایران', 'مازندران', 'ساری', 'مرکزی', 'ساری', 'منطقه یک', 'محله مرجع', 'خیابان الف', 'کوچه دوستی', 'مجتمع بهارستان', 'ساختمان ۳۵'],
        );
        $user = User::factory()->create();

        UserLocationRelationship::create([
            'user_id' => $user->id,
            'location_id' => $path->last()->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertViewHas('communityOptions', fn ($options): bool =>
            collect($options)->pluck('location.type.key')->values()->all() === ['street', 'alley', 'complex', 'building']
        );
        foreach (['خیابان الف', 'کوچه دوستی', 'مجتمع بهارستان', 'ساختمان ۳۵'] as $name) {
            $response->assertSee($name);
        }
        $response->assertSee('data-local-communities-tab', false);
        $response->assertSee('هرکدام می‌توانند اجتماع محلی مستقل خود را داشته باشند');
    }

}
