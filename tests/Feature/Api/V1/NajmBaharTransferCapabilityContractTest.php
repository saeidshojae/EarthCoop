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
            ->assertJsonCount(1, 'data.sources');

        $sources = collect($response->json('data.sources'))->keyBy('account_number');

        $this->assertTrue($sources->has($eligible->sub_account_code));
        $this->assertSame(900, $sources[$eligible->sub_account_code]['active_available_gol']);
        $this->assertTrue($sources[$eligible->sub_account_code]['can_transfer_active']);
        $this->assertSame('subaccount', $sources[$eligible->sub_account_code]['kind']);

        $this->assertFalse($sources->has($noMirror->sub_account_code));
        $this->assertFalse($sources->has($disabled->sub_account_code));
        $this->assertFalse($sources->has($foreign->sub_account_code));

        // Read-only projection must not create the missing mirror.
        $this->assertSame($beforeAccounts, Account::count());
        $this->assertDatabaseMissing('najm_accounts', [
            'account_number' => $noMirror->sub_account_code,
        ]);
    }


    public function test_capability_reports_no_eligible_source_when_all_active_sources_are_unspendable(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');

        $empty = $this->subAccount($main, '030', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($empty);

        $fullyReserved = $this->subAccount($main, '033', 600, 0, 1);
        $reservedMirror = app(AccountService::class)->ensureSubAccountAccount($fullyReserved);
        app(ActiveBaharReservationService::class)->reserve(
            $reservedMirror->account_number,
            600,
            'transfer-capability-fully-reserved',
            'test',
            33,
        );

        $this->openTransfers();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')
            ->assertOk()
            ->assertJsonPath('data.external_transfer_enabled', false)
            ->assertJsonPath('data.disabled_reason', 'no_eligible_source')
            ->assertJsonCount(2, 'data.sources')
            ->assertJsonPath('data.sources.0.can_transfer_active', false)
            ->assertJsonPath('data.sources.1.can_transfer_active', false);
    }

    public function test_capability_uses_canonical_mirror_availability_and_hides_inactive_mirrors(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');

        $drifted = $this->subAccount($main, '031', 1_500, 0, 1);
        $driftedMirror = app(AccountService::class)->ensureSubAccountAccount($drifted);
        $driftedMirror->balance_active = 800;
        $driftedMirror->balance = 800;
        $driftedMirror->save();

        $inactive = $this->subAccount($main, '032', 2_000, 0, 1);
        $inactiveMirror = app(AccountService::class)->ensureSubAccountAccount($inactive);
        $inactiveMirror->status = 0;
        $inactiveMirror->save();

        app(ActiveBaharReservationService::class)->reserve(
            $driftedMirror->account_number,
            300,
            'transfer-capability-mirror-reservation',
            'test',
            31,
        );

        $this->openTransfers();

        $sources = collect($this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')
            ->assertOk()
            ->json('data.sources'))
            ->keyBy('account_number');

        $this->assertSame(500, $sources[$drifted->sub_account_code]['active_available_gol']);
        $this->assertFalse($sources->has($inactive->sub_account_code));
    }

    public function test_destination_preview_resolves_exact_active_external_subaccount_with_minimal_identity(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create([
            'is_system' => false,
            'first_name' => 'Mina',
            'last_name' => 'Example',
        ]);

        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destination = $this->subAccount($otherMain, '004', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destination);

        $beforeAccounts = Account::count();
        $beforeTransactions = Transaction::count();
        $slash = str_replace('-', '/', $destination->sub_account_code);

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => '  '.$slash.'  ',
            ]))
            ->assertOk()
            ->assertJsonPath('data.account_number', $destination->sub_account_code)
            ->assertJsonPath('data.name', $destination->name)
            ->assertJsonPath('data.owner_type', 'user')
            ->assertJsonPath('data.owner_display_name', 'Mina Example')
            ->assertJsonPath('data.kind', 'subaccount')
            ->assertJsonPath('data.status', 1)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.balance')
            ->assertJsonMissingPath('data.account_id');

        $tokenValue = $response->json('data.destination_token');
        $this->assertIsString($tokenValue);
        $this->assertNotSame('', trim($tokenValue));
        $this->assertSame($beforeAccounts, Account::count());
        $this->assertSame($beforeTransactions, Transaction::count());
    }

    public function test_destination_preview_hides_missing_disabled_and_unmirrored_destinations(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);
        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');

        $disabled = $this->subAccount($otherMain, '005', 100, 0, 0);
        app(AccountService::class)->ensureSubAccountAccount($disabled);
        $unmirrored = $this->subAccount($otherMain, '006', 100, 0, 1);
        $inactiveMirrorDestination = $this->subAccount($otherMain, '008', 100, 0, 1);
        $inactiveMirror = app(AccountService::class)->ensureSubAccountAccount($inactiveMirrorDestination);
        $inactiveMirror->status = 0;
        $inactiveMirror->save();

        foreach ([
            '9999999999-999',
            $disabled->sub_account_code,
            $unmirrored->sub_account_code,
            $inactiveMirrorDestination->sub_account_code,
        ] as $number) {
            $this->bearer($token, $deviceId)
                ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                    'account_number' => $number,
                ]))
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'not_found');
        }
    }

    public function test_destination_preview_rejects_same_owner_destination_for_external_flow(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $own = $this->subAccount($main, '007', 100, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($own);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $own->sub_account_code,
            ]))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'transfer_destination_internal');
    }


    public function test_strict_native_transfer_binds_source_destination_amount_and_returns_matchable_receipt(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create([
            'is_system' => false,
            'first_name' => 'Receiver',
            'last_name' => 'Example',
        ]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $source = $this->subAccount($main, '011', 1_500, 0, 1);
        $sourceMirror = app(AccountService::class)->ensureSubAccountAccount($source);

        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destination = $this->subAccount($otherMain, '012', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destination);

        $this->openTransfers();

        $capability = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')
            ->assertOk()
            ->json('data');
        $sourceRow = collect($capability['sources'])
            ->firstWhere('account_number', $source->sub_account_code);

        $preview = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $destination->sub_account_code,
            ]))
            ->assertOk()
            ->json('data');

        $response = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-transfer-strict-0001')
            ->postJson('/api/v1/najm-bahar/transfers', [
                'source_account_id' => $sourceMirror->id,
                'destination_account_number' => $destination->sub_account_code,
                'amount_gol' => 250,
                'balance_bucket' => 'active',
                'description' => 'Native transfer',
                'expected' => [
                    'transfer_contract_version' => 1,
                    'source_account_number' => $source->sub_account_code,
                    'source_active_available_gol' => $sourceRow['active_available_gol'],
                    'destination_token' => $preview['destination_token'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.transaction.amount_gol', 250)
            ->assertJsonPath('data.transaction.balance_bucket', 'active')
            ->assertJsonPath('data.transaction.direction', 'outgoing')
            ->assertJsonPath('data.transaction.counterparty.account_number', $destination->sub_account_code);

        $this->assertIsInt($response->json('data.transaction.amount_gol'));
        $this->assertSame(1_250, (int) $source->fresh()->balance_active);
        $this->assertSame(250, (int) $destination->fresh()->balance_active);
    }

    public function test_strict_native_transfer_rejects_changed_source_availability_without_financial_mutation(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $source = $this->subAccount($main, '013', 1_500, 0, 1);
        $sourceMirror = app(AccountService::class)->ensureSubAccountAccount($source);
        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destination = $this->subAccount($otherMain, '014', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destination);
        $this->openTransfers();

        $sourceRow = collect($this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')->assertOk()->json('data.sources'))
            ->firstWhere('account_number', $source->sub_account_code);
        $preview = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $destination->sub_account_code,
            ]))->assertOk()->json('data');

        app(ActiveBaharReservationService::class)->reserve(
            $sourceMirror->account_number,
            100,
            'native-transfer-race-reservation',
            'test',
            1,
        );

        $beforeTransactions = Transaction::count();

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-transfer-race-0001')
            ->postJson('/api/v1/najm-bahar/transfers', [
                'source_account_id' => $sourceMirror->id,
                'destination_account_number' => $destination->sub_account_code,
                'amount_gol' => 250,
                'balance_bucket' => 'active',
                'expected' => [
                    'transfer_contract_version' => 1,
                    'source_account_number' => $source->sub_account_code,
                    'source_active_available_gol' => $sourceRow['active_available_gol'],
                    'destination_token' => $preview['destination_token'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'transfer_terms_changed');

        $this->assertSame($beforeTransactions, Transaction::count());
        $this->assertSame(1_500, (int) $source->fresh()->balance_active);
        $this->assertSame(0, (int) $destination->fresh()->balance_active);
    }

    public function test_strict_native_transfer_rejects_destination_token_mismatch_without_financial_mutation(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $source = $this->subAccount($main, '015', 1_500, 0, 1);
        $sourceMirror = app(AccountService::class)->ensureSubAccountAccount($source);
        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destinationA = $this->subAccount($otherMain, '016', 0, 0, 1);
        $destinationB = $this->subAccount($otherMain, '017', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destinationA);
        app(AccountService::class)->ensureSubAccountAccount($destinationB);
        $this->openTransfers();

        $sourceRow = collect($this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')->assertOk()->json('data.sources'))
            ->firstWhere('account_number', $source->sub_account_code);
        $previewA = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $destinationA->sub_account_code,
            ]))->assertOk()->json('data');

        $beforeTransactions = Transaction::count();

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-transfer-destination-mismatch-0001')
            ->postJson('/api/v1/najm-bahar/transfers', [
                'source_account_id' => $sourceMirror->id,
                'destination_account_number' => $destinationB->sub_account_code,
                'amount_gol' => 250,
                'balance_bucket' => 'active',
                'expected' => [
                    'transfer_contract_version' => 1,
                    'source_account_number' => $source->sub_account_code,
                    'source_active_available_gol' => $sourceRow['active_available_gol'],
                    'destination_token' => $previewA['destination_token'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'transfer_destination_changed');

        $this->assertSame($beforeTransactions, Transaction::count());
    }

    public function test_strict_native_transfer_never_allows_dim_external_movement(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $source = $this->subAccount($main, '018', 1_500, 500, 1);
        $sourceMirror = app(AccountService::class)->ensureSubAccountAccount($source);
        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destination = $this->subAccount($otherMain, '019', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destination);
        $this->openTransfers();

        $sourceRow = collect($this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')->assertOk()->json('data.sources'))
            ->firstWhere('account_number', $source->sub_account_code);
        $preview = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $destination->sub_account_code,
            ]))->assertOk()->json('data');

        $beforeTransactions = Transaction::count();

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-transfer-dim-block-0001')
            ->postJson('/api/v1/najm-bahar/transfers', [
                'source_account_id' => $sourceMirror->id,
                'destination_account_number' => $destination->sub_account_code,
                'amount_gol' => 100,
                'balance_bucket' => 'dim',
                'expected' => [
                    'transfer_contract_version' => 1,
                    'source_account_number' => $source->sub_account_code,
                    'source_active_available_gol' => $sourceRow['active_available_gol'],
                    'destination_token' => $preview['destination_token'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'transfer_not_allowed');

        $this->assertSame($beforeTransactions, Transaction::count());
        $this->assertSame(500, (int) $source->fresh()->balance_faded);
        $this->assertSame(0, (int) $destination->fresh()->balance_faded);
    }


    public function test_transfer_reconciliation_by_idempotency_returns_only_current_users_completed_receipt_without_writes(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create([
            'is_system' => false,
            'first_name' => 'Receiver',
            'last_name' => 'Example',
        ]);

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $source = $this->subAccount($main, '021', 2_000, 0, 1);
        $sourceMirror = app(AccountService::class)->ensureSubAccountAccount($source);

        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destination = $this->subAccount($otherMain, '022', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destination);

        $this->openTransfers();

        $sourceRow = collect($this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')->assertOk()->json('data.sources'))
            ->firstWhere('account_number', $source->sub_account_code);

        $preview = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $destination->sub_account_code,
            ]))->assertOk()->json('data');

        $key = 'native-transfer-reconcile-0001';
        $created = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/najm-bahar/transfers', [
                'source_account_id' => $sourceMirror->id,
                'destination_account_number' => $destination->sub_account_code,
                'amount_gol' => 275,
                'balance_bucket' => 'active',
                'expected' => [
                    'transfer_contract_version' => 1,
                    'source_account_number' => $source->sub_account_code,
                    'source_active_available_gol' => $sourceRow['active_available_gol'],
                    'destination_token' => $preview['destination_token'],
                ],
            ])
            ->assertCreated();

        $transactionId = (int) $created->json('data.transaction.id');
        $beforeTransactions = Transaction::count();
        $beforeAccounts = Account::count();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/by-idempotency/'.$key)
            ->assertOk()
            ->assertJsonPath('data.transaction.id', $transactionId)
            ->assertJsonPath('data.transaction.amount_gol', 275)
            ->assertJsonPath('data.transaction.balance_bucket', 'active')
            ->assertJsonPath('data.transaction.direction', 'outgoing')
            ->assertJsonPath('data.transaction.counterparty.account_number', $destination->sub_account_code)
            ->assertJsonMissingPath('data.transaction.metadata')
            ->assertJsonMissingPath('data.transaction.idempotency_key');

        $this->assertSame($beforeTransactions, Transaction::count());
        $this->assertSame($beforeAccounts, Account::count());
    }

    public function test_transfer_reconciliation_is_user_scoped_and_unknown_keys_are_not_found(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        [$other, $otherToken, $otherDeviceId] = $this->nativeSession();

        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member');
        $source = $this->subAccount($main, '023', 1_000, 0, 1);
        $sourceMirror = app(AccountService::class)->ensureSubAccountAccount($source);

        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other');
        $destination = $this->subAccount($otherMain, '024', 0, 0, 1);
        app(AccountService::class)->ensureSubAccountAccount($destination);

        $this->openTransfers();

        $sourceRow = collect($this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/capability')->assertOk()->json('data.sources'))
            ->firstWhere('account_number', $source->sub_account_code);

        $preview = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/destination?'.http_build_query([
                'account_number' => $destination->sub_account_code,
            ]))->assertOk()->json('data');

        $key = 'native-transfer-private-0001';
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/najm-bahar/transfers', [
                'source_account_id' => $sourceMirror->id,
                'destination_account_number' => $destination->sub_account_code,
                'amount_gol' => 100,
                'balance_bucket' => 'active',
                'expected' => [
                    'transfer_contract_version' => 1,
                    'source_account_number' => $source->sub_account_code,
                    'source_active_available_gol' => $sourceRow['active_available_gol'],
                    'destination_token' => $preview['destination_token'],
                ],
            ])
            ->assertCreated();

        $this->bearer($otherToken, $otherDeviceId)
            ->getJson('/api/v1/najm-bahar/transfers/by-idempotency/'.$key)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transfers/by-idempotency/native-transfer-unknown-0001')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
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

    private function openTransfers(): void
    {
        $setting = Setting::singleton();
        $setting->najm_bahar_user_threshold = User::count();
        $setting->save();
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
