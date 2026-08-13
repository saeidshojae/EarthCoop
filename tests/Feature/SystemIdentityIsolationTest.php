<?php

namespace Tests\Feature;

use App\Models\User;
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
}
