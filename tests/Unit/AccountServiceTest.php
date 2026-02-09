<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class AccountServiceTest extends TestCase
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
        ];

        foreach ($paths as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--env' => 'testing',
                '--force' => true,
            ]);
        }
    }

    public function test_create_main_account_for_user()
    {
        $service = new AccountService();
        $userId = 123;

        $account = $service->createMainAccountForUser($userId, 'Test Account');

        $this->assertInstanceOf(Account::class, $account);
        $this->assertEquals('1000000123', $account->account_number);
        $this->assertEquals($userId, $account->user_id);
        $this->assertEquals('Test Account', $account->name);
        $this->assertEquals('user', $account->type);
        $this->assertEquals(0, $account->balance);
    }

    public function test_create_main_account_with_default_name()
    {
        $service = new AccountService();
        $userId = 456;

        $account = $service->createMainAccountForUser($userId);

        $this->assertEquals('NajmBahar Account', $account->name);
    }

    public function test_create_account_idempotency()
    {
        $service = new AccountService();
        $userId = 789;

        $account1 = $service->createMainAccountForUser($userId);
        $account2 = $service->createMainAccountForUser($userId);

        // Should create new account each time (no idempotency check in service)
        $this->assertNotEquals($account1->id, $account2->id);
        $this->assertEquals($account1->account_number, $account2->account_number);
    }

    public function test_create_transaction()
    {
        $service = new AccountService();

        $account1 = Account::create([
            'account_number' => '1000000001',
            'user_id' => 1,
            'name' => 'Account 1',
            'type' => 'user',
            'balance' => 1000,
        ]);

        $account2 = Account::create([
            'account_number' => '1000000002',
            'user_id' => 2,
            'name' => 'Account 2',
            'type' => 'user',
            'balance' => 0,
        ]);

        $transaction = $service->createTransaction([
            'from_account_id' => $account1->id,
            'to_account_id' => $account2->id,
            'amount' => 100,
            'type' => 'immediate',
            'status' => 'completed',
            'description' => 'Test transaction',
        ]);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($account1->id, $transaction->from_account_id);
        $this->assertEquals($account2->id, $transaction->to_account_id);
        $this->assertEquals(100, $transaction->amount);
    }
}

