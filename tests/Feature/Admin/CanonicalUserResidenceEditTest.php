<?php

namespace Tests\Feature\Admin;

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

    public function test_admin_user_edit_includes_separate_canonical_residence_card(): void
    {
        $view = file_get_contents(resource_path('views/admin/user/edit.blade.php'));
        $partialPath = resource_path('views/admin/user/partials/canonical-residence.blade.php');

        $this->assertStringContainsString("@include('admin.user.partials.canonical-residence')", $view);
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
