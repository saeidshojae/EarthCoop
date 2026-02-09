<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\TransactionService;

class TransactionServiceTest extends TestCase
{
    // Don't run full app migrations; migrate only NajmBahar tables in testing env

    protected function setUp(): void
    {
        parent::setUp();

        // run only NajmBahar migrations against the testing database
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

    public function test_atomic_transfer_and_ledger_and_idempotency()
    {
        // create system and user accounts
        $system = Account::create([
            'account_number' => '0000000000',
            'name' => 'System',
            'type' => 'system',
            'balance' => 100000,
        ]);

        $user = Account::create([
            'account_number' => '1000000254',
            'name' => 'User 254',
            'type' => 'user',
            'balance' => 0,
        ]);

        $service = new TransactionService();

        // first transfer with idempotency key
        $tx1 = $service->transfer('0000000000', '1000000254', 10000, 'initial funding', [], 'idem-123');
        $this->assertNotNull($tx1->id);

        $system->refresh();
        $user->refresh();

        $this->assertEquals(90000, $system->balance);
        $this->assertEquals(10000, $user->balance);

        // ledger entries created
        $this->assertDatabaseHas('najm_ledger_entries', ['account_id' => $system->id, 'entry_type' => 'debit']);
        $this->assertDatabaseHas('najm_ledger_entries', ['account_id' => $user->id, 'entry_type' => 'credit']);

        // idempotent repeat should return same transaction and not change balances
        $tx2 = $service->transfer('0000000000', '1000000254', 10000, 'initial funding', [], 'idem-123');
        $this->assertEquals($tx1->id, $tx2->id);

        $system->refresh();
        $user->refresh();
        $this->assertEquals(90000, $system->balance);
        $this->assertEquals(10000, $user->balance);
    }
}
