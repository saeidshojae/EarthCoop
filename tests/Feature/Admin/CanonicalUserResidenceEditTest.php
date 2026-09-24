<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\SafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class CanonicalUserResidenceEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
        ]);

        // Keep Laravel's normal web middleware, especially SubstituteBindings,
        // active so {user} is bound exactly as it is in production. Only bypass
        // the admin authorization wrappers that are outside this feature's scope.
        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);
    }

    public function test_admin_residence_route_has_focused_controller_and_users_edit_permission(): void
    {
        $this->assertTrue(Route::has('admin.users.residence.update'));

        $route = Route::getRoutes()->getByName('admin.users.residence.update');
        $this->assertNotNull($route);
        $this->assertStringContainsString('UserResidenceController@update', $route->getActionName());
        $this->assertContains('permission:users.edit', $route->gatherMiddleware());
        $this->assertSame(['PUT'], $route->methods());
    }

    public function test_admin_user_edit_uses_flag_safe_canonical_wrapper_with_separate_residence_card(): void
    {
        $editRoute = Route::getRoutes()->getByName('admin.users.edit');
        $this->assertNotNull($editRoute);
        $this->assertStringContainsString('UserController@edit', $editRoute->getActionName());
        $this->assertContains('permission:users.edit', $editRoute->gatherMiddleware());
        $this->assertInstanceOf(SafeUserController::class, app(UserController::class));

        $safeController = file_get_contents(app_path('Http/Controllers/Admin/SafeUserController.php'));
        $wrapper = file_get_contents(resource_path('views/admin/user/edit_canonical.blade.php'));
        $partialPath = resource_path('views/admin/user/partials/canonical-residence.blade.php');

        $this->assertStringContainsString('public function edit(User $user)', $safeController);
        $this->assertStringContainsString("config('location-governance.registration_enabled')", $safeController);
        $this->assertStringContainsString("return parent::edit(\$user)", $safeController);
        $this->assertStringContainsString("view('admin.user.edit_canonical'", $safeController);
        $this->assertStringContainsString("@extends('admin.user.edit')", $wrapper);
        $this->assertStringContainsString('@parent', $wrapper);
        $this->assertStringContainsString("@include('admin.user.partials.canonical-residence')", $wrapper);
        $this->assertFileExists($partialPath);

        $partial = file_get_contents($partialPath);
        $this->assertStringContainsString('data-admin-user-residence', $partial);
        $this->assertStringContainsString('data-location-selector', $partial);
        $this->assertStringContainsString('data-location-selector-context="admin-user-residence"', $partial);
        $this->assertStringContainsString('name="location_id"', $partial);
        $this->assertStringContainsString('name="location_proposal_id"', $partial);
        $this->assertStringContainsString('name="reference_settlement_external_id"', $partial);
        $this->assertStringContainsString('data-reference-settlement-picker', $partial);
        $this->assertStringContainsString('data-reference-settlement-current-proposal-path', $partial);
        $this->assertStringContainsString('name="reason"', $partial);
        $this->assertStringContainsString("route('admin.users.residence.update', \$user)", $partial);
    }

    public function test_admin_canonical_residence_card_exposes_persisted_path_for_deep_type_first_hydration(): void
    {
        $partial = file_get_contents(resource_path('views/admin/user/partials/canonical-residence.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/SafeUserController.php'));

        $this->assertStringContainsString('data-location-current-id', $partial);
        $this->assertStringContainsString('data-location-current-proposal-id', $partial);
        $this->assertStringContainsString('data-location-current-path', $partial);
        $this->assertStringContainsString('residenceHydrationPath', $controller);
    }

    public function test_admin_can_move_user_to_approved_residence_with_actor_and_reason(): void
    {
        [$target, $oldHome, $newHome] = $this->makeApprovedMoveScenario();
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $newHome->id,
                'reason' => 'اصلاح محل سکونت با درخواست کاربر',
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasNoErrors();

        $current = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($newHome->id, $current->location_id);
        $this->assertTrue((bool) $current->explicit_transfer);
        $this->assertSame($admin->id, $current->changed_by_user_id);
        $this->assertSame('اصلاح محل سکونت با درخواست کاربر', $current->change_reason);
        $this->assertSame(1, $target->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
        $this->assertNotSame($oldHome->id, $current->location_id);
    }

    public function test_admin_approved_move_reconciles_canonical_memberships_when_groups_are_enabled(): void
    {
        config(['location-governance.groups_enabled' => true]);

        ['user' => $target, 'area' => $oldArea, 'endpoint' => $oldEndpoint] = MembershipFixture::canonicalUser();
        app(CanonicalGroupMembershipReconciler::class)->reconcile($target);

        $oldPublicGroup = Group::query()
            ->where('governance_area_id', $oldArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $newEndpoint = Location::factory()->create([
            'parent_id' => $oldEndpoint->parent_id,
            'location_schema_id' => $oldEndpoint->location_schema_id,
            'location_type_id' => $oldEndpoint->location_type_id,
            'country_code' => 'IR',
            'name' => 'ساری جدید مدیر',
            'canonical_name' => 'Admin New Sari',
            'level' => $oldEndpoint->level,
            'status' => 'active',
        ]);

        $newArea = GovernanceArea::query()->create([
            'key' => 'ir.admin-new-sari',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'Admin New Sari',
            'rank' => 10,
            'status' => 'active',
        ]);
        $newArea->locations()->attach($newEndpoint->id);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $newEndpoint->id,
                'reason' => 'انتقال تأییدشده توسط مدیر',
            ])
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHasNoErrors();

        $newPublicGroup = Group::query()
            ->where('governance_area_id', $newArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $this->assertSame(0, (int) $target->fresh()->groups()->whereKey($oldPublicGroup->id)->firstOrFail()->pivot->status);
        $this->assertSame(1, (int) $target->fresh()->groups()->whereKey($newPublicGroup->id)->firstOrFail()->pivot->status);
    }

    public function test_admin_can_select_open_proposal_as_pending_exact_residence_without_storing_proposal_as_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $target = User::factory()->create();
        $admin = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $anchor, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($target, $anchor, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی برای کاربر',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_proposal_id' => $proposal->id,
                'reason' => 'ثبت جزئیات دقیق محل سکونت توسط مدیر',
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasNoErrors();

        $current = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $target->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertSame($proposal->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
        $this->assertSame($admin->id, data_get($intent->metadata, 'actor_user_id'));
        $this->assertSame('ثبت جزئیات دقیق محل سکونت توسط مدیر', data_get($intent->metadata, 'reason'));
        $this->assertFalse(Location::query()->whereKey($proposal->id)->exists());
    }

    public function test_admin_can_edit_reference_settlement_and_deep_micro_address_with_audit_reason(): void
    {
        config([
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();
        LocationExternalId::query()->create([
            'location_id' => $anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-5555',
            'metadata' => ['fixture' => true],
        ]);
        $settlement = ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-99005',
            'parent_external_id' => 'IR-1404-5555',
            'source_code' => '99005',
            'source_row_id' => 99005,
            'name_fa' => 'آبادی مدیر',
            'search_name' => 'آبادی مدیر',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);

        $target = User::factory()->create();
        $admin = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $anchor, ['source' => 'test']);

        $service = app(LocationProposalService::class);
        $neighborhood = $service->proposeUnderReferenceSettlement(
            $target,
            $settlement,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله مدیر'],
        );
        $street = $service->proposeUnderProposal(
            $target,
            $neighborhood,
            $schema->types->firstWhere('key', 'street'),
            ['canonical_name' => 'خیابان مدیر'],
        );

        $reason = 'تکمیل نشانی دقیق کاربر توسط مدیر';
        $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'reference_settlement_external_id' => $settlement->external_id,
                'location_proposal_id' => $street->id,
                'reason' => $reason,
            ])
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHasNoErrors();

        $intent = $target->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();
        $this->assertSame($street->id, $intent->location_proposal_id);
        $this->assertNotNull($intent->reference_settlement_residence_claim_id);
        $this->assertSame($admin->id, data_get($intent->metadata, 'actor_user_id'));
        $this->assertSame($reason, data_get($intent->metadata, 'reason'));

        $this->actingAs($admin)->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('data-reference-settlement-picker', false)
            ->assertSee('data-reference-settlement-current-external-id="'.$settlement->external_id.'"', false)
            ->assertSee('data-reference-settlement-current-proposal-path', false);
    }

    public function test_admin_residence_update_requires_reason_and_exactly_one_selection(): void
    {
        [$target, $oldHome, $newHome] = $this->makeApprovedMoveScenario();
        $admin = User::factory()->create();

        $missingReason = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $newHome->id,
            ]);

        $missingReason->assertRedirect(route('admin.users.edit', $target));
        $missingReason->assertSessionHasErrors('reason');

        $both = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $newHome->id,
                'location_proposal_id' => 999999,
                'reason' => 'نباید دو انتخاب همزمان پذیرفته شود',
            ]);

        $both->assertRedirect(route('admin.users.edit', $target));
        $both->assertSessionHasErrors();
        $this->assertSame($oldHome->id, $target->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
    }

    public function test_admin_cannot_set_non_residence_endpoint_as_canonical_residence(): void
    {
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'], ['Iran'])->last();
        $home = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['Home Iran', 'Home Province', 'Home County', 'Home Section', 'Home City', 'Home Region', 'Home Neighborhood'],
        )->last();
        $target = User::factory()->create();
        $admin = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $home, ['source' => 'test']);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $country->id,
                'reason' => 'آزمون اعتبارسنجی',
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasErrors('location_id');
        $this->assertSame($home->id, $target->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
    }


    public function test_admin_transfer_preserves_structural_claim_dependency_support_and_audit_metadata(): void
    {
        $schema = LocationFixture::iranSchema();
        $oldHome = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $newPath = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);
        $city = $newPath->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $street = Location::query()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $streetType->id,
            'country_code' => 'IR',
            'canonical_name' => 'خیابان انتقال مدیر',
            'status' => 'active',
        ]);
        $target = User::factory()->create();
        $admin = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $oldHome, ['source' => 'test']);
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $target);

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $street->id,
                'location_structure_claim_ids' => [$claim->id],
                'reason' => 'اصلاح مسیر واقعی سکونت کاربر',
            ])
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHasNoErrors();

        $current = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($street->id, $current->location_id);
        $this->assertSame([$claim->id], $current->metadata['structural_claim_ids']);
        $this->assertSame($admin->id, $current->changed_by_user_id);
        $this->assertSame('اصلاح مسیر واقعی سکونت کاربر', $current->change_reason);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $target->id)->exists());
        $this->assertFalse($claim->fresh()->evidence()->where('user_id', $admin->id)->exists());
    }

    private function makeApprovedMoveScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $oldHome = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['Old Iran', 'Old Province', 'Old County', 'Old Section', 'Old City', 'Old Region', 'Old Neighborhood'],
        )->last();
        $newHome = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['New Iran', 'New Province', 'New County', 'New Section', 'New City', 'New Region', 'New Neighborhood'],
        )->last();
        $target = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $oldHome, ['source' => 'test']);

        return [$target, $oldHome, $newHome];
    }
    public function test_admin_can_preserve_structural_claims_when_selecting_direct_street_proposal(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $target = User::factory()->create();
        $admin = User::factory()->create();
        $claimService = app(LocationStructureClaimService::class);

        app(ResidenceService::class)->setInitialPrimaryResidence($target, $city, ['source' => 'test']);
        $noRegion = $claimService->findOrCreateOpenClaim($city, 'no_urban_region', $target);
        $noNeighborhood = $claimService->findOrCreateOpenClaim($city, 'no_neighborhood', $target);
        $proposal = app(LocationProposalService::class)->propose($target, $city, $streetType, [
            'canonical_name' => 'خیابان مستقیم مدیر',
        ], [$noRegion, $noNeighborhood]);

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_proposal_id' => $proposal->id,
                'location_structure_claim_ids' => [$noRegion->id, $noNeighborhood->id],
                'reason' => 'ثبت مسیر ساختاری واقعی کاربر',
            ])
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHasNoErrors();

        $current = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $current->location_id);
        $this->assertSame(
            [$noRegion->id, $noNeighborhood->id],
            collect($current->metadata['structural_claim_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all()
        );
        $this->assertSame(
            $proposal->id,
            $target->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id
        );
    }

}
