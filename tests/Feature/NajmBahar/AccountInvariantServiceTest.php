<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountInvariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountInvariantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_identifies_legacy_aggregate_main_balance_without_mutating_it(): void
    {
        $main = Account::create([
            'account_number' => 'AUDIT-MAIN-001',
            'name' => 'Main',
            'type' => 'user',
            'balance' => 1_000,
            'balance_active' => 400,
            'balance_faded' => 100,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'AUDIT-MAIN-001-001',
            'name' => 'Child',
            'balance' => 500,
            'balance_active' => 300,
            'balance_faded' => 200,
            'status' => 1,
        ]);

        Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Child',
            'type' => 'subaccount',
            'balance' => 500,
            'balance_active' => 300,
            'balance_faded' => 200,
        ]);

        $report = app(AccountInvariantService::class)->audit($main);

        $this->assertSame('legacy_aggregate_total', $report['balance_semantics']);
        $this->assertSame(500, $report['own_total']);
        $this->assertSame(500, $report['child_total']);
        $this->assertSame(1_000, $report['aggregate_total']);
        $this->assertTrue($report['is_clean']);
        $this->assertSame(1_000, (int) $main->fresh()->balance);
    }

    public function test_it_reports_subaccount_mirror_drift_and_command_is_read_only(): void
    {
        $main = Account::create([
            'account_number' => 'AUDIT-MAIN-002',
            'name' => 'Main',
            'type' => 'user',
            'balance' => 100,
            'balance_active' => 100,
            'balance_faded' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'AUDIT-MAIN-002-001',
            'name' => 'Child',
            'balance' => 50,
            'balance_active' => 50,
            'balance_faded' => 0,
            'status' => 1,
        ]);

        Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Child mirror',
            'type' => 'subaccount',
            'balance' => 40,
            'balance_active' => 40,
            'balance_faded' => 0,
        ]);

        $report = app(AccountInvariantService::class)->audit($main);
        $this->assertCount(1, $report['mirror_drift']);
        $this->assertFalse($report['is_clean']);

        Artisan::call('najm-bahar:audit-balances', ['--only-problems' => true]);

        $this->assertSame(100, (int) $main->fresh()->balance);
        $this->assertSame(50, (int) $sub->fresh()->balance);
        $this->assertSame(40, (int) Account::where('account_number', $sub->sub_account_code)->value('balance'));
    }
}
