<?php

namespace Tests\Feature\NajmBahar;

use App\Http\Controllers\Admin\SafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use App\Modules\NajmBahar\Models\MembershipRetirement;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MembershipRemovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipRemovalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_routes_resolve_to_estate_safe_controller(): void
    {
        $this->assertInstanceOf(SafeUserController::class, app(UserController::class));
    }

    public function test_admin_removal_retires_membership_without_deleting_identity_or_active_estate_wealth(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 1_000_000;
        $main->balance_active = 375_000;
        $main->balance = 1_375_000;
        $main->save();

        $retirement = app(MembershipRemovalService::class)->remove($user->id, [
            'initiated_by' => 'admin-test',
        ]);

        $this->assertNotNull($retirement);
        $this->assertSame('removal', $retirement->reason);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertSame(375_000, (int) $main->fresh()->balance_active);
        $this->assertSame(0, (int) $main->fresh()->balance_faded);
        $this->assertSame($user->id, (int) $main->fresh()->user_id);
        $this->assertTrue((bool) ($retirement->metadata['identity_preserved'] ?? false));
        $this->assertTrue((bool) ($retirement->metadata['estate_assets_preserved'] ?? false));
    }

    public function test_admin_removal_is_idempotent_and_does_not_duplicate_retirement(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 1_000_000;
        $main->balance_active = 125_000;
        $main->balance = 1_125_000;
        $main->save();

        $service = app(MembershipRemovalService::class);
        $first = $service->remove($user->id);
        $second = $service->remove($user->id);

        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(1, MembershipRetirement::where('user_id', $user->id)->count());
        $this->assertSame(125_000, (int) $main->fresh()->balance_active);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
    }

    public function test_legacy_user_without_najm_bahar_account_is_deactivated_not_hard_deleted(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $result = app(MembershipRemovalService::class)->remove($user->id);

        $this->assertNull($result);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertSame(0, MembershipRetirement::where('user_id', $user->id)->count());
    }
}
