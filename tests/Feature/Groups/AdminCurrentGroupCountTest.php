<?php

namespace Tests\Feature\Groups;

use App\Http\Controllers\Admin\UserController;
use App\Models\Group;
use App\Models\LocationScopedGroupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCurrentGroupCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_count_includes_pending_requests_without_counting_legacy_systemic_history(): void
    {
        config(['location-governance.groups_enabled' => true]);
        $user = User::factory()->create();

        $legacy = Group::query()->create([
            'name' => 'Historical city group',
            'group_type' => '0',
            'location_level' => 'city',
            'is_open' => 1,
        ]);
        $managed = Group::query()->create([
            'name' => 'User-managed group',
            'group_type' => '0',
            'location_level' => 10,
            'is_open' => 1,
        ]);
        $user->groups()->attach($legacy->id, ['role' => 0, 'status' => 1]);
        $user->groups()->attach($managed->id, ['role' => 1, 'status' => 1]);

        LocationScopedGroupRequest::query()->create([
            'requester_user_id' => $user->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'status' => 'pending_location',
            'metadata' => ['type_key' => 'neighborhood'],
        ]);

        $view = app(UserController::class)->show($user);
        $stats = $view->getData()['userStats'];
        $visible = $view->getData()['user']->groups->pluck('id')->all();

        $this->assertSame(2, $stats['groups_count']);
        $this->assertSame(1, $stats['pending_groups_count']);
        $this->assertSame([$managed->id], $visible);
    }
}
