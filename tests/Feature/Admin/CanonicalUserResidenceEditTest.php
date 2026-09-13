<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\SafeUserController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CanonicalUserResidenceEditTest extends TestCase
{
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
}
