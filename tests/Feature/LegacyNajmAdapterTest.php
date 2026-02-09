<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Modules\NajmBahar\Models\Account;
use App\Models\Spring;

class LegacyNajmAdapterTest extends TestCase
{
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

        // ensure Najm tables are clean (delete any previous test artifacts)
        $najmTables = [
            'najm_ledger_entries',
            'najm_transactions',
            'najm_sub_accounts',
            'najm_accounts',
        ];

        foreach ($najmTables as $t) {
            if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
                \Illuminate\Support\Facades\DB::table($t)->delete();
            }
        }

        // ensure queue runs synchronously in tests
        config(['queue.default' => 'sync']);
    }

    public function test_adapter_creates_accounts_and_funds_idempotent()
    {
        // create a transient Spring object (do NOT persist to DB to avoid touching other tables)
        $spring = new Spring([
            'name' => 'legacy spring test',
            'user_id' => 254,
            'amount' => 0,
            'status' => 'new',
        ]);
        // assign a synthetic id for idempotency keys; do not save
        $spring->id = 9999;

        // call the adapter directly (avoids creating springs table or running other migrations)
        \App\Modules\NajmBahar\Adapters\LegacyNajmAdapter::onSpringCreated($spring);

        // assert system account exists
        $this->assertDatabaseHas('najm_accounts', ['account_number' => '0000000000']);

        // assert user account exists
        $userAccNumber = (string)(1000000000 + 254);
        $this->assertDatabaseHas('najm_accounts', ['account_number' => $userAccNumber]);

        // assert the user received 10000 (initial funding) and then 12 membership deducted => net 9988
        $user = Account::where('account_number', $userAccNumber)->first();
        $this->assertNotNull($user);
        $this->assertEquals(9988, intval($user->balance));

        // Run the adapter again to simulate duplicate event processing
        \App\Modules\NajmBahar\Adapters\LegacyNajmAdapter::onSpringCreated($spring);

        $user->refresh();
        // balance should remain unchanged due to idempotency keys
        $this->assertEquals(9988, intval($user->balance));
    }
}
