<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountInvariantService;
use App\Modules\NajmBahar\Services\SafeTransactionService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeTransactionServiceInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_binding_routes_active_subaccount_system_spend_through_canonical_boundary(): void
    {
        $service = app(TransactionService::class);
        $this->assertInstanceOf(SafeTransactionService::class, $service);

        $main = Account::create([
            'account_number' => 'SAFE-MAIN-001',
            'name' => 'Main',
            'type' => 'user',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => 'SAFE-MAIN-001-001',
            'name' => 'Child',
            'balance' => 100,
            'balance_active' => 100,
            'balance_faded' => 0,
            'status' => 1,
        ]);

        $mirror = Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Child mirror',
            'type' => 'subaccount',
            'balance' => 100,
            'balance_active' => 100,
            'balance_faded' => 0,
            'committed_dim' => 77,
        ]);

        $system = Account::create([
            'account_number' => '0000000000-901',
            'name' => 'System sink',
            'type' => 'system',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $first = $service->transfer(
            $sub->sub_account_code,
            $system->account_number,
            25,
            'Release C canonical system spend',
            ['system_operation' => true],
            'safe-fallback-invariant-1',
            'active',
            'release_c_test'
        );
        $second = $service->transfer(
            $sub->sub_account_code,
            $system->account_number,
            25,
            'Release C canonical system spend replay',
            ['system_operation' => true],
            'safe-fallback-invariant-1',
            'active',
            'release_c_test'
        );

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame('sub_account_system_transfer_service', $first->metadata['routed_by'] ?? null);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $first->id)->count());
        $this->assertSame(75, (int) $sub->fresh()->balance_active);
        $this->assertSame(75, (int) $sub->fresh()->balance);
        $this->assertSame(75, (int) $mirror->fresh()->balance_active);
        $this->assertSame(0, (int) $mirror->fresh()->balance_faded);
        $this->assertSame(0, (int) $mirror->fresh()->committed_dim);
        $this->assertSame(75, (int) $mirror->fresh()->balance);
        $this->assertSame(75, (int) $main->fresh()->balance);
        $this->assertSame(25, (int) $system->fresh()->balance_active);
        $this->assertSame(25, (int) $system->fresh()->balance);
        $this->assertSame([], app(AccountInvariantService::class)->audit($main)['mirror_drift']);
        $this->assertSame('legacy_aggregate_total', app(AccountInvariantService::class)->audit($main)['balance_semantics']);
    }
}
