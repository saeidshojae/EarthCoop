<?php

namespace Tests\Feature\Api\V1;

use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharTransferCapabilityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_reports_threshold_lock_without_financial_mutation(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $sub = $this->subAccount($main, '001', 900, 100, 1);
        app(AccountService::class)->ensureSubAccountAccount($sub);

        $setting = Setting::singleton();
        $setting->najm_bahar_user_threshold = User::count() + 100;
        $setting->save();

        $beforeAccounts = Account::count();
        $beforeSubAccounts = SubAccount::count();
        $beforeTransactions = Transaction::count();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')
            ->assertOk()
            ->assertJsonPath('data.transfer_contract_version', 1)
            ->assertJsonPath('data.external_transfer_enabled', false)
            ->assertJsonPath('data.disabled_reason', 'threshold_not_met');

        $this->assertSame($beforeAccounts, Account::count());
        $this->assertSame($beforeSubAccounts, SubAccount::count());
        $this->assertSame($beforeTransactions, Transaction::count());
    }

    public function test_capability_lists_only_owned_active_subaccounts_with_reservation_aware_available_active(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $eligible = $this->subAccount($main, '001', 1_500, 400, 1);
        $disabled = $this->subAccount($main, '002', 5_000, 0, 0);
        $noMirror = $this->subAccount($main, '003', 700, 0, 1);

        $eligibleMirror = app(AccountService::class)->ensureSubAccountAccount($eligible);
        app(AccountService::class)->ensureSubAccountAccount($disabled);

        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $foreign = $this->subAccount($otherMain, '001', 9_000, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($foreign);

        app(ActiveBaharReservationService::class)->reserve(
            $eligibleMirror->account_number,
            600,
            'transfer-capability-reservation',
            'test',
            1,
        );

        $setting = Setting::singleton();
        $setting->najm_bahar_user_threshold = User::count();
        $setting->save();

        $beforeAccounts = Account::count();

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')
            ->assertOk()
            ->assertJsonPath('data.transfer_contract_version', 1)
            ->assertJsonPath('data.external_transfer_enabled', true)
            ->assertJsonPath('data.disabled_reason', null)
            ->assertJsonCount(2, 'data.sources');

        $sources = collect($response->json('data.sources'))->keyBy('account_number');

        $this->assertTrue($sources->has($eligible->sub_account_code));
        $this->assertSame(900, $sources[$eligible->sub_account_code]['active_available_gol']);
        $this->assertTrue($sources[$eligible->sub_account_code]['can_transfer_active']);
        $this->assertSame('subaccount', $sources[$eligible->sub_account_code]['kind']);

        $this->assertTrue($sources->has($noMirror->sub_account_code));
        $this->assertSame(700, $sources[$noMirror->sub_account_code]['active_available_gol']);
        $this->assertTrue($sources[$noMirror->sub_account_code]['can_transfer_active']);

        $this->assertFalse($sources->has($disabled->sub_account_code));
        $this->assertFalse($sources->has($foreign->sub_account_code));

        // Read-only projection must not create the missing mirror.
        $this->assertSame($beforeAccounts, Account::count());
        $this->assertDatabaseMissing('najm_accounts', [
            'account_number' => $noMirror->sub_account_code,
        ]);
    }

    private function subAccount(
        Account $main,
        string $suffix,
        int $active,
        int $dim,
        int $status,
    ): SubAccount {
        return SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => AccountNumberService::makeSubAccountCode($main->account_number, (int) $suffix),
            'name' => 'Wallet '.$suffix,
            'balance' => $active + $dim,
            'balance_active' => $active,
            'balance_faded' => $dim,
            'status' => $status,
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-transfer-capability-'.bin2hex(random_bytes(4)).'@example.test',
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
