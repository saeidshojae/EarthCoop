<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ScheduledTransactionTest extends TestCase
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

    public function test_process_scheduled_transactions_command()
    {
        // Create accounts
        $fromAccount = Account::create([
            'account_number' => '1000000001',
            'user_id' => 1,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        $toAccount = Account::create([
            'account_number' => '1000000002',
            'user_id' => 2,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        // Create scheduled transaction
        $transaction = NajmTransaction::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => null,
            'amount' => 100,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(), // Past time
        ]);

        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $transaction->id,
            'execute_at' => now()->subMinute(),
            'status' => 'scheduled',
            'attempts' => 0,
            'payload' => [
                'from_account_number' => $fromAccount->account_number,
                'to_account_number' => $toAccount->account_number,
                'amount' => 100,
                'description' => 'Scheduled transfer',
            ],
        ]);

        // Run command
        $this->artisan('najm-bahar:process-scheduled')
            ->expectsOutput('NajmBahar scheduled processing completed. Processed: 1')
            ->assertExitCode(0);

        // Verify transaction was processed
        $scheduled->refresh();
        $this->assertEquals('processed', $scheduled->status);
        $this->assertEquals(1, $scheduled->attempts);

        // Verify balances updated
        $fromAccount->refresh();
        $toAccount->refresh();
        $this->assertEquals(900, $fromAccount->balance);
        $this->assertEquals(100, $toAccount->balance);
    }

    public function test_scheduled_transaction_not_due_yet()
    {
        $fromAccount = Account::create([
            'account_number' => '1000000001',
            'user_id' => 1,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 1000,
        ]);

        $toAccount = Account::create([
            'account_number' => '1000000002',
            'user_id' => 2,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        $transaction = NajmTransaction::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => null,
            'amount' => 100,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => now()->addDay(),
        ]);

        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $transaction->id,
            'execute_at' => now()->addDay(), // Future time
            'status' => 'scheduled',
            'attempts' => 0,
            'payload' => [
                'from_account_number' => $fromAccount->account_number,
                'to_account_number' => $toAccount->account_number,
                'amount' => 100,
            ],
        ]);

        // Run command
        $this->artisan('najm-bahar:process-scheduled')
            ->expectsOutput('NajmBahar scheduled processing completed. Processed: 0')
            ->assertExitCode(0);

        // Verify transaction not processed
        $scheduled->refresh();
        $this->assertEquals('scheduled', $scheduled->status);
    }

    public function test_scheduled_transaction_failure_retry()
    {
        $fromAccount = Account::create([
            'account_number' => '1000000001',
            'user_id' => 1,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 50, // Insufficient funds
        ]);

        $toAccount = Account::create([
            'account_number' => '1000000002',
            'user_id' => 2,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        $transaction = NajmTransaction::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => null,
            'amount' => 100,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
        ]);

        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $transaction->id,
            'execute_at' => now()->subMinute(),
            'status' => 'scheduled',
            'attempts' => 0,
            'payload' => [
                'from_account_number' => $fromAccount->account_number,
                'to_account_number' => $toAccount->account_number,
                'amount' => 100,
            ],
        ]);

        // Run command
        $this->artisan('najm-bahar:process-scheduled')
            ->assertExitCode(0);

        // Verify transaction failed but still scheduled for retry
        $scheduled->refresh();
        $this->assertEquals('scheduled', $scheduled->status);
        $this->assertEquals(1, $scheduled->attempts);
        $this->assertNotNull($scheduled->last_error);
    }

    public function test_scheduled_transaction_max_attempts()
    {
        $fromAccount = Account::create([
            'account_number' => '1000000001',
            'user_id' => 1,
            'name' => 'From Account',
            'type' => 'user',
            'balance' => 50,
        ]);

        $toAccount = Account::create([
            'account_number' => '1000000002',
            'user_id' => 2,
            'name' => 'To Account',
            'type' => 'user',
            'balance' => 0,
        ]);

        $transaction = NajmTransaction::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id' => null,
            'amount' => 100,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
        ]);

        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $transaction->id,
            'execute_at' => now()->subMinute(),
            'status' => 'scheduled',
            'attempts' => 4, // One less than max
            'payload' => [
                'from_account_number' => $fromAccount->account_number,
                'to_account_number' => $toAccount->account_number,
                'amount' => 100,
            ],
        ]);

        // Run command
        $this->artisan('najm-bahar:process-scheduled')
            ->assertExitCode(0);

        // Verify transaction marked as failed after max attempts
        $scheduled->refresh();
        $this->assertEquals('failed', $scheduled->status);
        $this->assertEquals(5, $scheduled->attempts);
    }
}

