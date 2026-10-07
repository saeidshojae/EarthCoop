<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharSubAccountContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_subaccount_read_model_is_zero_write_and_reservation_aware(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $main->forceFill([
            'balance_active' => 1_000,
            'balance_faded' => 700,
            'committed_dim' => 200,
            'balance' => 1_900,
        ])->save();

        $sub = app(SubAccountService::class)->createSubAccount((int) $main->id, 'روزمره');
        $sub->forceFill([
            'balance_active' => 500,
            'balance_faded' => 300,
            'balance' => 800,
        ])->save();

        $mirror = Account::query()
            ->where('type', 'subaccount')
            ->where('account_number', $sub->sub_account_code)
            ->firstOrFail();
        $mirror->forceFill([
            'balance_active' => 500,
            'balance_faded' => 300,
            'balance' => 800,
        ])->save();

        app(ActiveBaharReservationService::class)->reserve(
            $main->account_number,
            250,
            'native-subaccount-main-reservation',
            'test',
            1,
        );
        app(ActiveBaharReservationService::class)->reserve(
            $mirror->account_number,
            125,
            'native-subaccount-child-reservation',
            'test',
            2,
        );

        $unmirrored = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => $main->account_number.'-099',
            'name' => 'بدون آینه',
            'balance' => 100,
            'balance_active' => 100,
            'balance_faded' => 0,
            'status' => 1,
        ]);

        $disabled = app(SubAccountService::class)->createSubAccount((int) $main->id, 'غیرفعال');
        $disabled->status = 0;
        $disabled->save();

        $beforeAccounts = Account::count();
        $beforeSubs = SubAccount::count();

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/subaccounts')
            ->assertOk()
            ->assertJsonPath('data.internal_transfer_contract_version', 1)
            ->assertJsonPath('data.main.account_id', $main->id)
            ->assertJsonPath('data.main.account_number', $main->account_number)
            ->assertJsonPath('data.main.active_gol', 1000)
            ->assertJsonPath('data.main.active_available_gol', 750)
            ->assertJsonPath('data.main.dim_available_gol', 700)
            ->assertJsonPath('data.main.dim_committed_gol', 200)
            ->assertJsonCount(1, 'data.subaccounts');

        $row = $response->json('data.subaccounts.0');
        $this->assertSame((int) $sub->id, $row['sub_account_id']);
        $this->assertSame((int) $mirror->id, $row['account_id']);
        $this->assertSame((string) $sub->sub_account_code, $row['account_number']);
        $this->assertSame('روزمره', $row['name']);
        $this->assertSame(1, $row['status']);
        $this->assertSame(500, $row['active_gol']);
        $this->assertSame(375, $row['active_available_gol']);
        $this->assertSame(300, $row['dim_available_gol']);

        foreach ([
            'data.main.active_gol',
            'data.main.active_available_gol',
            'data.main.dim_available_gol',
            'data.main.dim_committed_gol',
            'data.subaccounts.0.active_gol',
            'data.subaccounts.0.active_available_gol',
            'data.subaccounts.0.dim_available_gol',
        ] as $path) {
            $this->assertIsInt($response->json($path));
        }

        $this->assertSame($beforeAccounts, Account::count());
        $this->assertSame($beforeSubs, SubAccount::count());
        $this->assertDatabaseMissing('najm_accounts', [
            'account_number' => $unmirrored->sub_account_code,
        ]);
    }

    public function test_subaccount_read_model_is_current_user_scoped(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $own = app(SubAccountService::class)->createSubAccount((int) $main->id, 'خودم');

        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $foreign = app(SubAccountService::class)->createSubAccount((int) $otherMain->id, 'دیگری');

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/subaccounts')
            ->assertOk()
            ->assertJsonCount(1, 'data.subaccounts');

        $this->assertSame($own->sub_account_code, $response->json('data.subaccounts.0.account_number'));
        $this->assertNotSame($foreign->sub_account_code, $response->json('data.subaccounts.0.account_number'));
    }


    public function test_subaccount_create_is_idempotent_server_numbered_and_rejects_financial_authority_fields(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');

        $headers = ['Idempotency-Key' => 'subaccount-create-native-0001'];
        $payload = ['name' => '  روزمره  '];

        $first = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/subaccounts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'روزمره')
            ->assertJsonPath('data.status', 1)
            ->assertJsonPath('data.active_gol', 0)
            ->assertJsonPath('data.active_available_gol', 0)
            ->assertJsonPath('data.dim_available_gol', 0);

        $subId = (int) $first->json('data.sub_account_id');
        $accountId = (int) $first->json('data.account_id');
        $number = (string) $first->json('data.account_number');

        $this->assertGreaterThan(0, $subId);
        $this->assertGreaterThan(0, $accountId);
        $this->assertSame($main->account_number.'-001', $number);
        $this->assertDatabaseHas('najm_sub_accounts', [
            'id' => $subId,
            'account_id' => $main->id,
            'sub_account_code' => $number,
            'name' => 'روزمره',
            'status' => 1,
        ]);
        $this->assertDatabaseHas('najm_accounts', [
            'id' => $accountId,
            'account_number' => $number,
            'type' => 'subaccount',
            'name' => 'روزمره',
        ]);

        $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/subaccounts', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.sub_account_id', $subId)
            ->assertJsonPath('data.account_id', $accountId);

        $this->assertSame(1, SubAccount::query()->where('account_id', $main->id)->count());
        $this->assertSame(1, Account::query()->where('account_number', $number)->count());

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'subaccount-create-authority-0001')
            ->postJson('/api/v1/najm-bahar/subaccounts', [
                'name' => 'نام',
                'account_number' => 'forged',
                'status' => 1,
                'balance_active' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_subaccount_rename_is_owned_name_only_and_keeps_canonical_mirror_name_in_sync(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $sub = app(SubAccountService::class)->createSubAccount((int) $main->id, 'قدیمی');

        $sub->forceFill([
            'balance_active' => 123,
            'balance_faded' => 456,
            'balance' => 579,
        ])->save();
        $mirror = app(AccountService::class)->ensureSubAccountAccount($sub->fresh());

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'subaccount-rename-native-0001')
            ->patchJson('/api/v1/najm-bahar/subaccounts/'.$sub->id, [
                'name' => '  پس‌انداز  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.sub_account_id', $sub->id)
            ->assertJsonPath('data.name', 'پس‌انداز')
            ->assertJsonPath('data.active_gol', 123)
            ->assertJsonPath('data.dim_available_gol', 456);

        $this->assertSame('پس‌انداز', $sub->fresh()->name);
        $this->assertSame('پس‌انداز', $mirror->fresh()->name);
        $this->assertSame(123, (int) $sub->fresh()->balance_active);
        $this->assertSame(456, (int) $sub->fresh()->balance_faded);

        $other = User::factory()->create(['is_system' => false]);
        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $foreign = app(SubAccountService::class)->createSubAccount((int) $otherMain->id, 'Foreign');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'subaccount-rename-foreign-0001')
            ->patchJson('/api/v1/najm-bahar/subaccounts/'.$foreign->id, [
                'name' => 'هک',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'subaccount-rename-authority-0001')
            ->patchJson('/api/v1/najm-bahar/subaccounts/'.$sub->id, [
                'name' => 'مجاز',
                'status' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(1, (int) $sub->fresh()->status);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-subaccounts-'.bin2hex(random_bytes(4)).'@example.test',
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
