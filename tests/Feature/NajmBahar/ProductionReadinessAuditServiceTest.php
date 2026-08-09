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

        $transaction = $this->completedTransaction($from, $to, 25);
        $this->balancedLedger($transaction, $from, $to, 25);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['account_failures']);
        $this->assertSame([], $result['ledger_failures']);
        $this->assertSame([], $result['idempotency_failures']);
        $this->assertSame([], $result['recovery_failures']);
        $this->assertSame(2, $result['accounts_checked']);
        $this->assertSame(1, $result['completed_transactions_checked']);
    }

    public function test_missing_credit_entry_fails_readiness_audit(): void
    {
        $from = $this->account('HARDEN-BAD-FROM', 100);
        $to = $this->account('HARDEN-BAD-TO', 50);
        $transaction = $this->completedTransaction($from, $to, 25);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $from->id,
            'amount' => -25,
            'entry_type' => 'debit',
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['ledger_failures']);
        $this->assertSame('double_entry_ledger_must_have_exactly_two_entries', $result['ledger_failures'][0]['issue']);
        $this->assertSame($transaction->id, $result['ledger_failures'][0]['transaction_id']);
    }

    public function test_wrong_ledger_endpoint_fails_readiness_audit(): void
    {
        $from = $this->account('HARDEN-ENDPOINT-FROM', 100);
        $to = $this->account('HARDEN-ENDPOINT-TO', 50);
        $wrong = $this->account('HARDEN-ENDPOINT-WRONG', 0);
        $transaction = $this->completedTransaction($from, $to, 25);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $wrong->id,
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

        $this->assertFalse($result['ok']);
        $this->assertSame('debit_entry_does_not_match_transaction', $result['ledger_failures'][0]['issue']);
    }

    public function test_duplicate_idempotency_key_fails_readiness_audit(): void
    {
        $from = $this->account('HARDEN-IDEM-FROM', 100);
        $to = $this->account('HARDEN-IDEM-TO', 50);

        foreach ([10, 15] as $amount) {
            $transaction = NajmTransaction::create([
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => ['idempotency_key' => 'same-request-key'],
                'description' => 'Duplicate idempotency fixture',
            ]);
            $this->balancedLedger($transaction, $from, $to, $amount);
        }

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['idempotency_failures']);
        $this->assertSame('duplicate_idempotency_key', $result['idempotency_failures'][0]['issue']);
        $this->assertSame('same-request-key', $result['idempotency_failures'][0]['idempotency_key']);
        $this->assertCount(2, $result['idempotency_failures'][0]['transaction_ids']);
    }

    public function test_failed_transaction_with_ledger_effects_fails_recovery_audit(): void
    {
        $from = $this->account('HARDEN-RECOVERY-FROM', 100);
        $to = $this->account('HARDEN-RECOVERY-TO', 50);

        $transaction = NajmTransaction::create([
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 25,
            'type' => 'immediate',
            'status' => 'failed',
            'description' => 'Partial failure fixture',
        ]);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $from->id,
            'amount' => -25,
            'entry_type' => 'debit',
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['recovery_failures']);
        $this->assertSame('non_completed_transaction_has_ledger_effects', $result['recovery_failures'][0]['issue']);
        $this->assertSame('failed', $result['recovery_failures'][0]['status']);
        $this->assertSame(1, $result['recovery_failures'][0]['ledger_entry_count']);
    }

    private function completedTransaction(Account $from, Account $to, int $amount): NajmTransaction
    {
        return NajmTransaction::create([
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => $amount,
            'type' => 'immediate',
            'status' => 'completed',
            'description' => 'Hardening completed transaction fixture',
        ]);
    }

    private function balancedLedger(NajmTransaction $transaction, Account $from, Account $to, int $amount): void
    {
        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $from->id,
            'amount' => -$amount,
            'entry_type' => 'debit',
        ]);
        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $to->id,
            'amount' => $amount,
            'entry_type' => 'credit',
        ]);
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
