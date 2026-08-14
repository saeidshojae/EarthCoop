<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupUser;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\AccountService;
use Database\Seeders\SystemUserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class SystemIdentityIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_seeder_creates_only_a_non_member_support_identity(): void
    {
        $this->seed(SystemUserSeeder::class);

        $support = User::where('email', 'support@earthcoop.ir')->firstOrFail();

        $this->assertTrue($support->isSystemIdentity());
        $this->assertFalse((bool) $support->is_admin);
        $this->assertFalse(User::members()->whereKey($support->id)->exists());
        $this->assertFalse(User::where('email', 'test@example.com')->exists());
        $this->assertFalse(Account::where('user_id', $support->id)->exists());
    }

    public function test_system_identity_cannot_receive_a_najm_bahar_account(): void
    {
        $this->seed(SystemUserSeeder::class);
        $support = User::where('email', 'support@earthcoop.ir')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        app(AccountService::class)->createMainAccountForUser($support->id);
    }

    public function test_system_identity_cannot_sign_in_interactively(): void
    {
        $support = User::create([
            'email' => 'system@example.test',
            'password' => Hash::make('known-password'),
            'is_system' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $support->email,
            'password' => 'known-password',
        ]);

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_existing_system_identity_session_is_terminated_before_web_access(): void
    {
        $support = User::create([
            'email' => 'stale-system-session@example.test',
            'password' => Hash::make('known-password'),
            'is_system' => true,
        ]);

        $response = $this->actingAs($support)->get('/home');

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_system_group_presence_is_not_counted_as_cooperative_membership(): void
    {
        $group = Group::create(['name' => 'System identity isolation']);
        $member = User::create([
            'email' => 'real-member@example.test',
            'password' => Hash::make('known-password'),
            'is_system' => false,
        ]);
        $bot = User::create([
            'email' => 'system-bot@example.test',
            'password' => Hash::make('known-password'),
            'is_system' => true,
        ]);

        GroupUser::where('group_id', $group->id)->forceDelete();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $bot->id, 'role' => 3, 'status' => 1]);

        $this->assertSame([$member->id], $group->users()->pluck('users.id')->all());
        $this->assertSame(1, $group->userCount());
        $this->assertSame([$bot->id], $group->systemUsers()->pluck('users.id')->all());
    }
}
