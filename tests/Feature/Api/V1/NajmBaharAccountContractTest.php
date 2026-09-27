<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharAccountContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_user_account_exposes_canonical_integer_gol_balance_projection(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();

        $account = Account::create([
            'account_number' => '100000'.$user->id,
            'user_id' => $user->id,
            'name' => 'Main account',
            'type' => 'user',
            'balance' => 999,
            'balance_active' => 1_500_000_001,
            'balance_faded' => 2_000_000_003,
            'committed_dim' => 400_000_005,
            'status' => 1,
        ]);

        SubAccount::create([
            'account_id' => $account->id,
            'sub_account_code' => $account->account_number.'-001',
            'name' => 'Project reserve',
            'balance' => 300,
            'balance_active' => 100,
            'balance_faded' => 200,
            'status' => 1,
        ]);

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/account')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.account_number', $account->account_number)
            ->assertJsonPath('data.balance.local.active_gol', 1_500_000_001)
            ->assertJsonPath('data.balance.local.dim_available_gol', 2_000_000_003)
            ->assertJsonPath('data.balance.local.dim_committed_gol', 400_000_005)
            ->assertJsonPath('data.balance.local.dim_total_gol', 2_400_000_008)
            ->assertJsonPath('data.balance.local.total_gol', 3_900_000_009)
            ->assertJsonPath('data.balance.aggregate.active_gol', 1_500_000_101)
            ->assertJsonPath('data.balance.aggregate.dim_available_gol', 2_000_000_203)
            ->assertJsonPath('data.balance.aggregate.dim_committed_gol', 400_000_005)
            ->assertJsonPath('data.balance.aggregate.dim_total_gol', 2_400_000_208)
            ->assertJsonPath('data.balance.aggregate.total_gol', 3_900_000_309)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.balance_faded')
            ->assertJsonMissingPath('data.committed_dim')
            ->assertJsonMissingPath('data.meta');

        foreach ([
            'data.balance.local.active_gol',
            'data.balance.local.dim_available_gol',
            'data.balance.local.dim_committed_gol',
            'data.balance.local.dim_total_gol',
            'data.balance.local.total_gol',
            'data.balance.aggregate.active_gol',
            'data.balance.aggregate.dim_available_gol',
            'data.balance.aggregate.dim_committed_gol',
            'data.balance.aggregate.dim_total_gol',
            'data.balance.aggregate.total_gol',
        ] as $path) {
            $this->assertIsInt($response->json($path), $path.' must remain an integer Gol value.');
        }
    }

    public function test_explicit_account_balance_lookup_is_owner_scoped_and_hides_cross_user_accounts(): void
    {
        [$owner, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $ownedAccount = Account::create([
            'account_number' => 'OWNED-'.$owner->id,
            'user_id' => $owner->id,
            'name' => 'Owned',
            'type' => 'user',
            'balance' => 30,
            'balance_active' => 10,
            'balance_faded' => 15,
            'committed_dim' => 5,
            'status' => 1,
        ]);

        $otherAccount = Account::create([
            'account_number' => 'OTHER-'.$other->id,
            'user_id' => $other->id,
            'name' => 'Other',
            'type' => 'user',
            'balance' => 60,
            'balance_active' => 20,
            'balance_faded' => 30,
            'committed_dim' => 10,
            'status' => 1,
        ]);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/accounts/'.$ownedAccount->id.'/balance')
            ->assertOk()
            ->assertJsonPath('data.account_id', $ownedAccount->id)
            ->assertJsonPath('data.local.total_gol', 30);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/accounts/'.$otherAccount->id.'/balance')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_account_read_does_not_provision_or_mutate_money_when_member_has_no_account(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();

        $this->assertDatabaseMissing('najm_accounts', ['user_id' => $user->id]);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/account')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->assertDatabaseMissing('najm_accounts', ['user_id' => $user->id]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-account-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => false,
        ])->assertCreated();

        return [$user, (string) $login->json('data.token'), (string) $login->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
