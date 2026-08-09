<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_issuance_is_ledger_backed_and_idempotent(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'account_number' => '9100000001',
            'user_id' => $user->id,
            'name' => 'Test Najm Bahar Account',
            'type' => 'user',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $service = app(MonetaryService::class);
        $first = $service->issueMembershipCredit($account, $user->id);
        $second = $service->issueMembershipCredit($account, $user->id);

        $expected = BaharMoney::toGolFromBahar(10_000);
        $account->refresh();

        $this->assertTrue($first['applied']);
        $this->assertFalse($second['applied']);
        $this->assertSame($expected, (int) $account->balance);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame($expected, (int) $account->balance_faded);

        $transaction = Transaction::where('metadata->idempotency_key', 'membership-issuance-' . $user->id)
            ->firstOrFail();

        $this->assertSame(1, Transaction::where('metadata->idempotency_key', 'membership-issuance-' . $user->id)->count());
        $this->assertSame(1, LedgerEntry::where('transaction_id', $transaction->id)->count());
        $this->assertSame($expected, (int) LedgerEntry::where('transaction_id', $transaction->id)->value('amount'));
        $this->assertSame('faded', data_get(LedgerEntry::where('transaction_id', $transaction->id)->first()->meta, 'balance_bucket'));
    }

    public function test_activation_moves_dim_to_active_without_changing_total_supply(): void
    {
        $user = User::factory()->create();
        $total = BaharMoney::toGolFromBahar(10_000);
        $account = Account::create([
            'account_number' => '9100000002',
            'user_id' => $user->id,
            'name' => 'Activation Account',
            'type' => 'user',
            'balance' => $total,
            'balance_active' => 0,
            'balance_faded' => $total,
        ]);

        $amount = BaharMoney::toGolFromBahar(10);
        $service = app(MonetaryService::class);

        $result = $service->activateDim(
            $account,
            $amount,
            'test activation',
            ['type' => 'test_activation'],
            'test-activation-' . $user->id,
            false
        );

        $account->refresh();

        $this->assertTrue($result['applied']);
        $this->assertSame($total, (int) $account->balance);
        $this->assertSame($amount, (int) $account->balance_active);
        $this->assertSame($total - $amount, (int) $account->balance_faded);

        $entries = LedgerEntry::where('transaction_id', $result['transaction']->id)->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(-$amount, (int) $entries[0]->amount);
        $this->assertSame('faded', data_get($entries[0]->meta, 'balance_bucket'));
        $this->assertSame($amount, (int) $entries[1]->amount);
        $this->assertSame('active', data_get($entries[1]->meta, 'balance_bucket'));
        $this->assertSame(0, (int) $entries->sum('amount'));
    }

    public function test_activation_is_idempotent_and_partial_activation_never_exceeds_dim_balance(): void
    {
        $user = User::factory()->create();
        $available = BaharMoney::toGolFromBahar(6);
        $account = Account::create([
            'account_number' => '9100000003',
            'user_id' => $user->id,
            'name' => 'Partial Activation Account',
            'type' => 'user',
            'balance' => $available,
            'balance_active' => 0,
            'balance_faded' => $available,
        ]);

        $service = app(MonetaryService::class);
        $key = 'auto-activation-test-' . $user->id;

        $first = $service->activateDim(
            $account,
            BaharMoney::toGolFromBahar(10),
            'automatic activation',
            ['type' => 'automatic_activation'],
            $key,
            true
        );
        $second = $service->activateDim(
            $account,
            BaharMoney::toGolFromBahar(10),
            'automatic activation',
            ['type' => 'automatic_activation'],
            $key,
            true
        );

        $account->refresh();

        $this->assertTrue($first['applied']);
        $this->assertFalse($second['applied']);
        $this->assertSame($available, $first['amount']);
        $this->assertSame(0, (int) $account->balance_faded);
        $this->assertSame($available, (int) $account->balance_active);
        $this->assertSame($available, (int) $account->balance);
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', $key)->count());
    }

    public function test_failed_non_partial_activation_leaves_balances_and_ledger_unchanged(): void
    {
        $user = User::factory()->create();
        $available = BaharMoney::toGolFromBahar(4);
        $account = Account::create([
            'account_number' => '9100000004',
            'user_id' => $user->id,
            'name' => 'Insufficient Dim Account',
            'type' => 'user',
            'balance' => $available,
            'balance_active' => 0,
            'balance_faded' => $available,
        ]);

        $beforeTransactions = Transaction::count();
        $beforeLedger = LedgerEntry::count();

        try {
            app(MonetaryService::class)->activateDim(
                $account,
                BaharMoney::toGolFromBahar(10),
                'must fail atomically',
                ['type' => 'atomic_failure_test'],
                'atomic-failure-' . $user->id,
                false
            );
            $this->fail('Expected insufficient dim funds exception was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Insufficient', $exception->getMessage());
        }

        $account->refresh();
        $this->assertSame($available, (int) $account->balance);
        $this->assertSame($available, (int) $account->balance_faded);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame($beforeTransactions, Transaction::count());
        $this->assertSame($beforeLedger, LedgerEntry::count());
    }

    public function test_activation_on_subaccount_mirror_reconciles_child_through_invariant_service(): void
    {
        $parent = Account::create([
            'account_number' => '9100000010',
            'name' => 'Mirror parent',
            'type' => 'user',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $sub = SubAccount::create([
            'account_id' => $parent->id,
            'sub_account_code' => '9100000010-001',
            'name' => 'Mirror child',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 100,
            'status' => 1,
        ]);

        $mirror = Account::create([
            'account_number' => $sub->sub_account_code,
            'name' => 'Mirror account',
            'type' => 'subaccount',
            'balance' => 100,
            'balance_active' => 0,
            'balance_faded' => 100,
            'committed_dim' => 12,
        ]);

        $service = app(MonetaryService::class);
        $first = $service->activateDim(
            $mirror,
            25,
            'sub-account mirror activation',
            ['type' => 'release_c_mirror_activation'],
            'release-c-mirror-activation-1',
            false
        );
        $second = $service->activateDim(
            $mirror,
            25,
            'sub-account mirror activation replay',
            ['type' => 'release_c_mirror_activation'],
            'release-c-mirror-activation-1',
            false
        );

        $this->assertTrue($first['applied']);
        $this->assertFalse($second['applied']);
        $this->assertSame((int) $first['transaction']->id, (int) $second['transaction']->id);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $first['transaction']->id)->count());

        $mirror->refresh();
        $sub->refresh();

        $this->assertSame(25, (int) $mirror->balance_active);
        $this->assertSame(75, (int) $mirror->balance_faded);
        $this->assertSame(0, (int) $mirror->committed_dim);
        $this->assertSame(100, (int) $mirror->balance);
        $this->assertSame(25, (int) $sub->balance_active);
        $this->assertSame(75, (int) $sub->balance_faded);
        $this->assertSame(100, (int) $sub->balance);
    }
}
