<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\DimCommitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DimCommitmentSubAccountInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_dim_commitment_is_rejected_on_subaccount_mirror_without_mutation(): void
    {
        $user = User::factory()->create();
        $parent = Account::create([
            'account_number' => '9200000001',
            'user_id' => $user->id,
            'name' => 'Parent',
            'type' => 'user',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 0,
            'committed_dim' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $parent->id,
            'sub_account_code' => '9200000001-001',
            'name' => 'Child',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 100,
            'status' => 1,
        ]);

        $mirror = Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Child mirror',
            'type' => 'subaccount',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 100,
            'committed_dim' => 0,
        ]);

        $beforeTransactions = Transaction::count();
        $beforeLedger = LedgerEntry::count();

        try {
            app(DimCommitmentService::class)->commit(
                $mirror,
                50,
                'uat-subaccount-commitment',
                'UAT commitment must be rejected on mirror',
                ['type' => 'uat']
            );
            $this->fail('Expected sub-account mirror commitment to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sub-account', strtolower($exception->getMessage()));
        }

        $mirror->refresh();
        $sub->refresh();

        $this->assertSame(100, (int) $mirror->balance);
        $this->assertSame(100, (int) $mirror->balance_faded);
        $this->assertSame(0, (int) $mirror->committed_dim);
        $this->assertSame(100, (int) $sub->balance);
        $this->assertSame(100, (int) $sub->balance_faded);
        $this->assertSame($beforeTransactions, Transaction::count());
        $this->assertSame($beforeLedger, LedgerEntry::count());
    }
}
