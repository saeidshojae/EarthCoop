<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\ProductionReadinessAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame([], $result['operational_failures']);
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

    public function test_historical_duplicate_metadata_idempotency_key_fails_readiness_audit(): void
    {
        $from = $this->account('HARDEN-IDEM-FROM', 100);
        $to = $this->account('HARDEN-IDEM-TO', 50);
        $transactions = [];

        foreach ([10, 15] as $amount) {
            $transaction = NajmTransaction::create([
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'description' => 'Historical duplicate idempotency fixture',
            ]);
            $transactions[] = $transaction;
            $this->balancedLedger($transaction, $from, $to, $amount);

            DB::table('najm_transactions')
                ->where('id', $transaction->id)
                ->update([
                    'metadata' => json_encode(['idempotency_key' => 'same-request-key']),
                    'idempotency_key' => null,
                ]);
        }

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['idempotency_failures']);
        $this->assertSame('duplicate_idempotency_key', $result['idempotency_failures'][0]['issue']);
        $this->assertSame('same-request-key', $result['idempotency_failures'][0]['idempotency_key']);
        $this->assertSame(
            array_map(fn (NajmTransaction $transaction) => (int) $transaction->id, $transactions),
            $result['idempotency_failures'][0]['transaction_ids']
        );
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

    public function test_overdue_pending_scheduled_transaction_fails_operational_audit(): void
    {
        $placeholder = $this->pendingPlaceholder();
        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $placeholder->id,
            'execute_at' => now()->subMinute(),
            'status' => 'pending',
            'attempts' => 0,
            'payload' => [],
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['operational_failures']);
        $this->assertSame('scheduled_transaction_overdue', $result['operational_failures'][0]['issue']);
        $this->assertSame((int) $scheduled->id, $result['operational_failures'][0]['scheduled_transaction_id']);
    }

    public function test_failed_scheduled_transaction_reports_attempts_for_operator_review(): void
    {
        $placeholder = $this->pendingPlaceholder('failed');
        ScheduledTransaction::create([
            'transaction_id' => $placeholder->id,
            'execute_at' => now()->subMinutes(5),
            'status' => 'failed',
            'attempts' => 4,
            'payload' => [],
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertSame('failed_scheduled_transaction_requires_operator_review', $result['operational_failures'][0]['issue']);
        $this->assertSame(4, $result['operational_failures'][0]['attempts']);
    }

    public function test_scheduled_transaction_without_placeholder_fails_operational_audit(): void
    {
        ScheduledTransaction::create([
            'transaction_id' => null,
            'execute_at' => now()->addMinute(),
            'status' => 'pending',
            'attempts' => 0,
            'payload' => [],
        ]);

        $result = app(ProductionReadinessAuditService::class)->run();

        $this->assertFalse($result['ok']);
        $this->assertSame('scheduled_transaction_missing_placeholder', $result['operational_failures'][0]['issue']);
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

    private function pendingPlaceholder(string $status = 'pending'): NajmTransaction
    {
        return NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => null,
            'amount' => 0,
            'type' => 'scheduled',
            'status' => $status,
            'description' => 'Scheduled readiness placeholder fixture',
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
