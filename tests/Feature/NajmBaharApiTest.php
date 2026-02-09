<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\AccountNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

class NajmBaharApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run NajmBahar migrations
        $paths = [
            'database/migrations/2025_11_22_000001_create_najm_accounts_table.php',
            'database/migrations/2025_11_22_000002_create_najm_sub_accounts_table.php',
            'database/migrations/2025_11_22_000003_create_najm_transactions_table.php',
            'database/migrations/2025_11_22_000004_create_najm_scheduled_transactions_table.php',
            'database/migrations/2025_11_22_000005_create_najm_ledger_entries_table.php',
        ];

        foreach ($paths as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--env' => 'testing',
                '--force' => true,
            ]);
        }
    }

    public function test_create_account_endpoint()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/najm-bahar/accounts', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'account' => [
                'id',
                'account_number',
                'user_id',
                'name',
                'type',
                'balance',
            ],
        ]);

        $this->assertDatabaseHas('najm_accounts', [
            'user_id' => $user->id,
            'account_number' => AccountNumberService::makeMainAccountNumberForUser($user->id),
        ]);
    }

    public function test_get_balance_endpoint()
    {
        $user = User::factory()->create();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);

        Account::create([
            'account_number' => $accountNumber,
            'user_id' => $user->id,
            'name' => 'Test Account',
            'type' => 'user',
            'balance' => 5000,
        ]);

        $response = $this->getJson("/api/najm-bahar/accounts/{$accountNumber}/balance");

        $response->assertStatus(200);
        $response->assertJson([
            'balance' => 5000,
        ]);
    }

    public function test_get_balance_not_found()
    {
        $response = $this->getJson('/api/najm-bahar/accounts/9999999999/balance');

        $response->assertStatus(404);
    }

    public function test_transfer_endpoint_requires_authentication()
    {
        $response = $this->postJson('/api/najm-bahar/transactions/transfer', [
            'to_account_number' => '1000000002',
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }

    public function test_transfer_endpoint()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $fromAccountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $toAccountNumber = '1000000002';

        // Create accounts
        Account::create([
            'account_number' => $fromAccountNumber,
            'user_id' => $user->id,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        Account::create([
            'account_number' => $toAccountNumber,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/najm-bahar/transactions/transfer', [
            'to_account_number' => $toAccountNumber,
            'amount' => 100,
            'description' => 'Test transfer',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'transaction' => [
                'id',
                'from_account_id',
                'to_account_id',
                'amount',
                'status',
            ],
        ]);

        // Verify balances updated
        $fromAccount = Account::where('account_number', $fromAccountNumber)->first();
        $toAccount = Account::where('account_number', $toAccountNumber)->first();

        $this->assertEquals(900, $fromAccount->balance);
        $this->assertEquals(100, $toAccount->balance);
    }

    public function test_transfer_insufficient_funds()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $fromAccountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $toAccountNumber = '1000000002';

        Account::create([
            'account_number' => $fromAccountNumber,
            'user_id' => $user->id,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 50, // Less than transfer amount
        ]);

        Account::create([
            'account_number' => $toAccountNumber,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/najm-bahar/transactions/transfer', [
            'to_account_number' => $toAccountNumber,
            'amount' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Insufficient funds',
        ]);
    }

    public function test_transfer_idempotency()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $fromAccountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $toAccountNumber = '1000000002';
        $idempotencyKey = 'test-idempotency-' . time();

        Account::create([
            'account_number' => $fromAccountNumber,
            'user_id' => $user->id,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        Account::create([
            'account_number' => $toAccountNumber,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        // First transfer
        $response1 = $this->postJson('/api/najm-bahar/transactions/transfer', [
            'to_account_number' => $toAccountNumber,
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response1->assertStatus(201);
        $transactionId1 = $response1->json('transaction.id');

        // Second transfer with same idempotency key
        $response2 = $this->postJson('/api/najm-bahar/transactions/transfer', [
            'to_account_number' => $toAccountNumber,
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response2->assertStatus(200);
        $transactionId2 = $response2->json('transaction.id');

        // Should return same transaction
        $this->assertEquals($transactionId1, $transactionId2);

        // Balance should not change
        $fromAccount = Account::where('account_number', $fromAccountNumber)->first();
        $this->assertEquals(900, $fromAccount->balance);
    }

    public function test_schedule_transaction()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $fromAccountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $toAccountNumber = '1000000002';

        Account::create([
            'account_number' => $fromAccountNumber,
            'user_id' => $user->id,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        Account::create([
            'account_number' => $toAccountNumber,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        $executeAt = now()->addDays(1)->toIso8601String();

        $response = $this->postJson('/api/najm-bahar/transactions/schedule', [
            'to_account_number' => $toAccountNumber,
            'amount' => 100,
            'execute_at' => $executeAt,
            'description' => 'Scheduled transfer',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'scheduled' => [],
            'transaction' => [
                'id',
                'type',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('najm_transactions', [
            'type' => 'scheduled',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('najm_scheduled_transactions', [
            'status' => 'scheduled',
        ]);
    }

    public function test_list_transactions()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::create([
            'account_number' => $accountNumber,
            'user_id' => $user->id,
            'name' => 'Test Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        // Create some transactions
        \App\Modules\NajmBahar\Models\Transaction::create([
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 100,
            'type' => 'immediate',
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/najm-bahar/transactions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'type',
                    'status',
                ],
            ],
        ]);
    }

    public function test_get_ledger()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::create([
            'account_number' => $accountNumber,
            'user_id' => $user->id,
            'name' => 'Test Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        // Create ledger entry
        \App\Modules\NajmBahar\Models\LedgerEntry::create([
            'account_id' => $account->id,
            'amount' => 100,
            'entry_type' => 'credit',
        ]);

        $response = $this->getJson("/api/najm-bahar/ledger/{$accountNumber}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'account_id',
                    'amount',
                    'entry_type',
                ],
            ],
        ]);
    }
}

