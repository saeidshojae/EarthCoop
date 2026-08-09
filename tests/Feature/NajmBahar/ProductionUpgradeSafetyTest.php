<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionUpgradeSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_prefers_a_complete_existing_mirror_money_state_without_changing_total(): void
    {
        $main = Account::create([
            'account_number' => 'UPGRADE-MAIN-001',
            'name' => 'Upgrade Main',
            'type' => 'user',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'UPGRADE-SUB-001',
            'name' => 'Legacy Child',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 0,
            'status' => 1,
        ]);

        $mirror = Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Legacy Child Mirror',
            'type' => 'subaccount',
            'balance' => 100,
            'balance_active' => 40,
            'balance_faded' => 60,
        ]);

        $this->runLegacySubAccountBackfill();

        $sub->refresh();
        $mirror->refresh();

        $this->assertSame(100, (int) $sub->balance);
        $this->assertSame(40, (int) $sub->balance_active);
        $this->assertSame(60, (int) $sub->balance_faded);
        $this->assertSame(100, (int) $mirror->balance);
        $this->assertSame(40, (int) $mirror->balance_active);
        $this->assertSame(60, (int) $mirror->balance_faded);
    }

    public function test_upgrade_classifies_fully_unbucketed_legacy_subaccount_as_dim_without_changing_total(): void
    {
        $main = Account::create([
            'account_number' => 'UPGRADE-MAIN-002',
            'name' => 'Upgrade Main',
            'type' => 'user',
            'balance' => 70,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'UPGRADE-SUB-002',
            'name' => 'Unbucketed Child',
            'balance' => 70,
            'balance_active' => 0,
            'balance_faded' => 0,
            'status' => 1,
        ]);

        $mirror = Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Unbucketed Mirror',
            'type' => 'subaccount',
            'balance' => 70,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $this->runLegacySubAccountBackfill();

        $sub->refresh();
        $mirror->refresh();

        $this->assertSame(70, (int) $sub->balance);
        $this->assertSame(0, (int) $sub->balance_active);
        $this->assertSame(70, (int) $sub->balance_faded);
        $this->assertSame(70, (int) $mirror->balance);
        $this->assertSame(0, (int) $mirror->balance_active);
        $this->assertSame(70, (int) $mirror->balance_faded);
    }

    public function test_upgrade_is_idempotent_and_does_not_reclassify_existing_money_state(): void
    {
        $main = Account::create([
            'account_number' => 'UPGRADE-MAIN-003',
            'name' => 'Upgrade Main',
            'type' => 'user',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'UPGRADE-SUB-003',
            'name' => 'Classified Child',
            'balance' => 100,
            'balance_active' => 25,
            'balance_faded' => 75,
            'status' => 1,
        ]);

        Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Classified Mirror',
            'type' => 'subaccount',
            'balance' => 100,
            'balance_active' => 25,
            'balance_faded' => 75,
        ]);

        $this->runLegacySubAccountBackfill();
        $this->runLegacySubAccountBackfill();

        $sub->refresh();

        $this->assertSame(100, (int) $sub->balance);
        $this->assertSame(25, (int) $sub->balance_active);
        $this->assertSame(75, (int) $sub->balance_faded);
    }

    private function runLegacySubAccountBackfill(): void
    {
        $migration = require database_path('migrations/2026_08_09_130000_backfill_legacy_subaccount_money_state.php');
        $migration->up();
    }
}
