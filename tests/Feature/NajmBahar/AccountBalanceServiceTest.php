<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_balance_ignores_legacy_stored_total_and_uses_only_money_buckets(): void
    {
        $account = Account::create([
            'account_number' => 'BALANCE-LOCAL-001',
            'name' => 'Main',
            'type' => 'user',
            'balance' => 9_999,
            'balance_active' => 400,
            'balance_faded' => 600,
        ]);

        $local = app(AccountBalanceService::class)->local($account);

        $this->assertSame(400, $local['active']);
        $this->assertSame(600, $local['dim']);
        $this->assertSame(1_000, $local['total']);
    }

    public function test_aggregate_balance_sums_main_and_child_subaccounts_once(): void
    {
        $main = Account::create([
            'account_number' => 'BALANCE-AGG-001',
            'name' => 'Main',
            'type' => 'user',
            'balance' => 1_600, // legacy aggregate value; must not be double counted
            'balance_active' => 300,
            'balance_faded' => 200,
        ]);

        SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'BALANCE-AGG-001-001',
            'name' => 'One',
            'balance' => 700,
            'balance_active' => 500,
            'balance_faded' => 200,
            'status' => 1,
        ]);

        SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'BALANCE-AGG-001-002',
            'name' => 'Two',
            'balance' => 400,
            'balance_active' => 100,
            'balance_faded' => 300,
            'status' => 1,
        ]);

        $aggregate = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame(900, $aggregate['active']);
        $this->assertSame(700, $aggregate['dim']);
        $this->assertSame(1_600, $aggregate['total']);
        $this->assertSame(500, $aggregate['main']['total']);
        $this->assertSame(1_100, $aggregate['subaccounts']['total']);
        $this->assertSame(2, $aggregate['subaccounts']['count']);
    }
}
