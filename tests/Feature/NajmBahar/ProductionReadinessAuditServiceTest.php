<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\ProductionReadinessAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_completed_transaction_passes_readiness_audit(): void
    {
        $from = $this->account('HARDEN-FROM', 100);
        $to = $this->account('HARDEN-TO', 50);

        $transaction = NajmTransaction::create([
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 25,
            'type' => 'immediate',
            'status' => 'completed',
            'description' => 'Hardening balanced ledger fixture',
        ]);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $from->id,
            'amount' => -25,
            'entry_type' => 'debit',
        ]);
        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $to->id,
            'amount' => 25,
            'entry_type' => 'credit',
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['account_failures']);
        $this->assertSame([], $result['ledger_failures']);
        $this->assertSame(2, $result['accounts_checked']);
        $this->assertSame(1, $result['completed_transactions_checked']);
    }

    public function test_missing_credit_entry_fails_readiness_audit(): void
    {
        $from = $this->account('HARDEN-BAD-FROM', 100);
        $to = $this->account('HARDEN-BAD-TO', 50);

        $transaction = NajmTransaction::create([
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 25,
            'type' => 'immediate',
            'status' => 'completed',
            'description' => 'Hardening corrupt ledger fixture',
        ]);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $from->id,
            'amount' => -25,
            'entry_type' => 'debit',
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['ledger_failures']);
        $this->assertSame('unbalanced_double_entry_ledger', $result['ledger_failures'][0]['issue']);
        $this->assertSame($transaction->id, $result['ledger_failures'][0]['transaction_id']);
    }

    private function account(string $number, int $active): Account
    {
        return Account::create([
            'account_number' => $number,
            'name' => $number,
            'type' => 'user',
            'balance' => $active,
            'balance_active' => $active,
            'balance_faded' => 0,
            'committed_dim' => 0,
        ]);
    }
}
