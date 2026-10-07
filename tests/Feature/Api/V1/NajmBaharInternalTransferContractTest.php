<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharInternalTransferContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_main_to_sub_active_is_exact_idempotent_and_preserves_aggregate_wealth(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = $this->main($user, active: 1000, dim: 400, committed: 100);
        $sub = app(SubAccountService::class)->createSubAccount((int) $main->id, 'روزمره');

        $snapshot = $this->subaccountSnapshot($token, $deviceId);
        $before = app(AccountBalanceService::class)->aggregate($main->fresh());

        $payload = [
            'direction' => 'main_to_sub',
            'source_sub_account_id' => null,
            'destination_sub_account_id' => $sub->id,
            'amount_gol' => 250,
            'balance_bucket' => 'active',
            'description' => 'Internal active allocation',
            'expected' => [
                'internal_transfer_contract_version' => 1,
                'source_account_number' => $snapshot['main']['account_number'],
                'source_available_gol' => $snapshot['main']['active_available_gol'],
                'destination_account_number' => $sub->sub_account_code,
            ],
        ];
        $headers = ['Idempotency-Key' => 'internal-main-sub-active-0001'];

        $first = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/internal-transfers', $payload)
            ->assertCreated()
            ->assertJsonPath('data.transaction.status', 'completed')
            ->assertJsonPath('data.transaction.direction', 'internal')
            ->assertJsonPath('data.transaction.balance_bucket', 'active')
            ->assertJsonPath('data.transaction.amount_gol', 250)
            ->assertJsonPath('data.source.account_number', $main->account_number)
            ->assertJsonPath('data.destination.account_number', $sub->sub_account_code);

        $transactionId = $first->json('data.transaction.id');
        $this->assertSame(750, (int) $main->fresh()->balance_active);
        $this->assertSame(250, (int) $sub->fresh()->balance_active);
        $this->assertSame(2, LedgerEntry::query()->where('transaction_id', $transactionId)->count());

        $after = app(AccountBalanceService::class)->aggregate($main->fresh());
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['active'], $after['active']);
        $this->assertSame($before['dim'], $after['dim']);

        $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/internal-transfers', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.transaction.id', $transactionId);

        $this->assertSame(750, (int) $main->fresh()->balance_active);
        $this->assertSame(250, (int) $sub->fresh()->balance_active);
    }

    public function test_native_sub_to_sub_dim_preserves_dim_and_total(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = $this->main($user, active: 0, dim: 500, committed: 0);
        $from = app(SubAccountService::class)->createSubAccount((int) $main->id, 'از');
        $to = app(SubAccountService::class)->createSubAccount((int) $main->id, 'به');

        app(\App\Modules\NajmBahar\Services\InternalAccountTransferService::class)
            ->mainToSub($main, $from, 300, 'faded', 'Seed dim', 'seed-internal-dim');

        $snapshot = $this->subaccountSnapshot($token, $deviceId);
        $fromRow = collect($snapshot['subaccounts'])->firstWhere('sub_account_id', $from->id);
        $before = app(AccountBalanceService::class)->aggregate($main->fresh());

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'internal-sub-sub-dim-0001')
            ->postJson('/api/v1/najm-bahar/internal-transfers', [
                'direction' => 'sub_to_sub',
                'source_sub_account_id' => $from->id,
                'destination_sub_account_id' => $to->id,
                'amount_gol' => 125,
                'balance_bucket' => 'dim',
                'description' => null,
                'expected' => [
                    'internal_transfer_contract_version' => 1,
                    'source_account_number' => $fromRow['account_number'],
                    'source_available_gol' => $fromRow['dim_available_gol'],
                    'destination_account_number' => $to->sub_account_code,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.transaction.direction', 'internal')
            ->assertJsonPath('data.transaction.balance_bucket', 'dim')
            ->assertJsonPath('data.transaction.amount_gol', 125);

        $this->assertSame(175, (int) $from->fresh()->balance_faded);
        $this->assertSame(125, (int) $to->fresh()->balance_faded);

        $after = app(AccountBalanceService::class)->aggregate($main->fresh());
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['dim'], $after['dim']);
        $this->assertSame($before['active'], $after['active']);
    }

    public function test_native_internal_transfer_stale_active_snapshot_fails_closed_without_mutation(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = $this->main($user, active: 1000, dim: 0, committed: 0);
        $sub = app(SubAccountService::class)->createSubAccount((int) $main->id, 'مقصد');

        $snapshot = $this->subaccountSnapshot($token, $deviceId);
        app(ActiveBaharReservationService::class)->reserve(
            $main->account_number,
            300,
            'internal-stale-reservation',
            'test',
            1,
        );

        $beforeMain = $main->fresh();
        $beforeSub = $sub->fresh();
        $beforeLedger = LedgerEntry::count();

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'internal-stale-active-0001')
            ->postJson('/api/v1/najm-bahar/internal-transfers', [
                'direction' => 'main_to_sub',
                'source_sub_account_id' => null,
                'destination_sub_account_id' => $sub->id,
                'amount_gol' => 200,
                'balance_bucket' => 'active',
                'expected' => [
                    'internal_transfer_contract_version' => 1,
                    'source_account_number' => $snapshot['main']['account_number'],
                    'source_available_gol' => $snapshot['main']['active_available_gol'],
                    'destination_account_number' => $sub->sub_account_code,
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'internal_transfer_terms_changed');

        $this->assertSame((int) $beforeMain->balance_active, (int) $main->fresh()->balance_active);
        $this->assertSame((int) $beforeSub->balance_active, (int) $sub->fresh()->balance_active);
        $this->assertSame($beforeLedger, LedgerEntry::count());
    }

    public function test_native_internal_transfer_rejects_foreign_subaccount_and_same_subaccount(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = $this->main($user, active: 500, dim: 0, committed: 0);
        $own = app(SubAccountService::class)->createSubAccount((int) $main->id, 'خودم');

        $other = User::factory()->create(['is_system' => false]);
        $otherMain = $this->main($other, active: 0, dim: 0, committed: 0);
        $foreign = app(SubAccountService::class)->createSubAccount((int) $otherMain->id, 'دیگری');

        $snapshot = $this->subaccountSnapshot($token, $deviceId);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'internal-foreign-0001')
            ->postJson('/api/v1/najm-bahar/internal-transfers', [
                'direction' => 'main_to_sub',
                'source_sub_account_id' => null,
                'destination_sub_account_id' => $foreign->id,
                'amount_gol' => 100,
                'balance_bucket' => 'active',
                'expected' => [
                    'internal_transfer_contract_version' => 1,
                    'source_account_number' => $snapshot['main']['account_number'],
                    'source_available_gol' => $snapshot['main']['active_available_gol'],
                    'destination_account_number' => $foreign->sub_account_code,
                ],
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        app(\App\Modules\NajmBahar\Services\InternalAccountTransferService::class)
            ->mainToSub($main, $own, 200, 'active', 'Seed', 'seed-own-active');
        $snapshot = $this->subaccountSnapshot($token, $deviceId);
        $ownRow = collect($snapshot['subaccounts'])->firstWhere('sub_account_id', $own->id);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'internal-same-sub-0001')
            ->postJson('/api/v1/najm-bahar/internal-transfers', [
                'direction' => 'sub_to_sub',
                'source_sub_account_id' => $own->id,
                'destination_sub_account_id' => $own->id,
                'amount_gol' => 50,
                'balance_bucket' => 'active',
                'expected' => [
                    'internal_transfer_contract_version' => 1,
                    'source_account_number' => $ownRow['account_number'],
                    'source_available_gol' => $ownRow['active_available_gol'],
                    'destination_account_number' => $ownRow['account_number'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'internal_transfer_not_allowed');
    }


    public function test_internal_transfer_reconciliation_returns_original_success_receipt_without_writes(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = $this->main($user, active: 800, dim: 200, committed: 0);
        $sub = app(SubAccountService::class)->createSubAccount((int) $main->id, 'مصرف');

        $snapshot = $this->subaccountSnapshot($token, $deviceId);
        $key = 'internal-reconcile-0001';

        $created = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/najm-bahar/internal-transfers', [
                'direction' => 'main_to_sub',
                'source_sub_account_id' => null,
                'destination_sub_account_id' => $sub->id,
                'amount_gol' => 175,
                'balance_bucket' => 'active',
                'description' => 'Reconcile me',
                'expected' => [
                    'internal_transfer_contract_version' => 1,
                    'source_account_number' => $snapshot['main']['account_number'],
                    'source_available_gol' => $snapshot['main']['active_available_gol'],
                    'destination_account_number' => $sub->sub_account_code,
                ],
            ])
            ->assertCreated();

        $transactionId = (int) $created->json('data.transaction.id');
        $beforeTransactions = \App\Modules\NajmBahar\Models\Transaction::count();
        $beforeLedger = LedgerEntry::count();
        $beforeAccounts = Account::count();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/internal-transfers/by-idempotency/'.$key)
            ->assertOk()
            ->assertJsonPath('data.transaction.id', $transactionId)
            ->assertJsonPath('data.transaction.status', 'completed')
            ->assertJsonPath('data.transaction.direction', 'internal')
            ->assertJsonPath('data.transaction.balance_bucket', 'active')
            ->assertJsonPath('data.transaction.amount_gol', 175)
            ->assertJsonPath('data.source.account_number', $main->account_number)
            ->assertJsonPath('data.destination.account_number', $sub->sub_account_code)
            ->assertJsonMissingPath('data.transaction.metadata')
            ->assertJsonMissingPath('data.idempotency_key');

        $this->assertSame($beforeTransactions, \App\Modules\NajmBahar\Models\Transaction::count());
        $this->assertSame($beforeLedger, LedgerEntry::count());
        $this->assertSame($beforeAccounts, Account::count());
    }

    public function test_internal_transfer_reconciliation_is_user_scoped_and_unknown_keys_are_not_found(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        [$other, $otherToken, $otherDeviceId] = $this->nativeSession();

        $main = $this->main($user, active: 500, dim: 0, committed: 0);
        $sub = app(SubAccountService::class)->createSubAccount((int) $main->id, 'مقصد');
        $snapshot = $this->subaccountSnapshot($token, $deviceId);
        $key = 'internal-private-0001';

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/najm-bahar/internal-transfers', [
                'direction' => 'main_to_sub',
                'source_sub_account_id' => null,
                'destination_sub_account_id' => $sub->id,
                'amount_gol' => 100,
                'balance_bucket' => 'active',
                'expected' => [
                    'internal_transfer_contract_version' => 1,
                    'source_account_number' => $snapshot['main']['account_number'],
                    'source_available_gol' => $snapshot['main']['active_available_gol'],
                    'destination_account_number' => $sub->sub_account_code,
                ],
            ])
            ->assertCreated();

        $this->bearer($otherToken, $otherDeviceId)
            ->getJson('/api/v1/najm-bahar/internal-transfers/by-idempotency/'.$key)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/internal-transfers/by-idempotency/internal-unknown-0001')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    private function subaccountSnapshot(string $token, string $deviceId): array
    {
        return $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/subaccounts')
            ->assertOk()
            ->json('data');
    }

    private function main(User $user, int $active, int $dim, int $committed): Account
    {
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member '.$user->id);
        $main->forceFill([
            'balance_active' => $active,
            'balance_faded' => $dim,
            'committed_dim' => $committed,
            'balance' => $active + $dim + $committed,
        ])->save();

        return $main->fresh();
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-internal-'.bin2hex(random_bytes(4)).'@example.test',
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
