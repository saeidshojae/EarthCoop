<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\SafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Models\Location;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class CanonicalUserResidenceEditTest extends TestCase
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
        $this->assertStringContainsString('name="reason"', $partial);
        $this->assertStringContainsString("route('admin.users.residence.update', \$user)", $partial);
    }

    public function test_admin_can_move_user_to_approved_residence_with_actor_reason_and_reconciliation(): void
    {
        [$target, $oldHome, $newHome] = $this->makeApprovedMoveScenario();
        $admin = User::factory()->create();

        $reconciler = $this->mock(CanonicalGroupMembershipReconciler::class);
        $reconciler->shouldReceive('reconcile')
            ->once()
            ->withArgs(fn (User $user): bool => (int) $user->id === (int) $target->id);
        config(['location-governance.groups_enabled' => true]);

        $response = $this->withoutMiddleware()
            ->actingAs($admin)
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

        $response = $this->withoutMiddleware()
            ->actingAs($admin)
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

    public function test_admin_residence_update_requires_reason_and_exactly_one_selection(): void
    {
        [$target, $oldHome, $newHome] = $this->makeApprovedMoveScenario();
        $admin = User::factory()->create();

        $missingReason = $this->withoutMiddleware()
            ->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $newHome->id,
            ]);

        $missingReason->assertRedirect(route('admin.users.edit', $target));
        $missingReason->assertSessionHasErrors('reason');

        $both = $this->withoutMiddleware()
            ->actingAs($admin)
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

        $response = $this->withoutMiddleware()
            ->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_id' => $country->id,
                'reason' => 'آزمون اعتبارسنجی',
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasErrors('location_id');
        $this->assertSame($home->id, $target->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
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
}
