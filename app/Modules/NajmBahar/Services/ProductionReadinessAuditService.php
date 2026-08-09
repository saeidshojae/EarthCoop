<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;

class ProductionReadinessAuditService
{
    public function run(): array
    {
        $accountAudits = app(AccountInvariantService::class)->auditAllMainAccounts()->values();
        $accountFailures = $accountAudits->filter(fn (array $audit) => ! ($audit['is_clean'] ?? false))->values();

        $completedTransactions = NajmTransaction::query()
            ->where('status', 'completed')
            ->orderBy('id')
            ->get();

        $ledgerFailures = $completedTransactions
            ->map(fn (NajmTransaction $transaction) => $this->auditCompletedTransaction($transaction))
            ->filter()
            ->values();

        $idempotencyFailures = $this->auditDuplicateIdempotencyKeys();
        $recoveryFailures = $this->auditNonCompletedLedgerEffects();

        return [
            'ok' => $accountFailures->isEmpty()
                && $ledgerFailures->isEmpty()
                && $idempotencyFailures->isEmpty()
                && $recoveryFailures->isEmpty(),
            'accounts_checked' => $accountAudits->count(),
            'account_failures' => $accountFailures->all(),
            'completed_transactions_checked' => $completedTransactions->count(),
            'ledger_failures' => $ledgerFailures->all(),
            'idempotency_failures' => $idempotencyFailures->all(),
            'recovery_failures' => $recoveryFailures->all(),
        ];
    }

    private function auditCompletedTransaction(NajmTransaction $transaction): ?array
    {
        $entries = LedgerEntry::where('transaction_id', $transaction->id)->orderBy('id')->get();

        if ($entries->isEmpty()) {
            return $this->failure($transaction, 'missing_ledger_entries');
        }

        $classificationOnly = $entries->contains(
            fn (LedgerEntry $entry) => (bool) data_get($entry->meta, 'classification_only', false)
        );

        if ($classificationOnly) {
            if ($entries->count() !== 1 || (int) $entries->first()->amount !== 0) {
                return $this->failure($transaction, 'invalid_classification_ledger');
            }

            return null;
        }

        $fromExternal = $transaction->from_account_id === null;
        $toExternal = $transaction->to_account_id === null;

        if ($fromExternal && $toExternal) {
            return $this->failure($transaction, 'completed_transaction_has_no_financial_endpoint');
        }

        if ($fromExternal xor $toExternal) {
            if ($entries->count() !== 1) {
                return $this->failure($transaction, 'external_monetary_event_must_have_one_entry');
            }

            $entry = $entries->first();
            $expected = $fromExternal ? (int) $transaction->amount : -((int) $transaction->amount);
            $expectedAccountId = $fromExternal
                ? (int) $transaction->to_account_id
                : (int) $transaction->from_account_id;

            if ((int) $entry->amount !== $expected) {
                return $this->failure($transaction, 'external_monetary_event_amount_mismatch');
            }

            if ((int) $entry->account_id !== $expectedAccountId) {
                return $this->failure($transaction, 'external_monetary_event_account_mismatch');
            }

            return null;
        }

        if ($entries->count() !== 2) {
            return $this->failure($transaction, 'double_entry_ledger_must_have_exactly_two_entries');
        }

        $debit = $entries->first(fn (LedgerEntry $entry) => $entry->entry_type === 'debit');
        $credit = $entries->first(fn (LedgerEntry $entry) => $entry->entry_type === 'credit');

        if (! $debit || ! $credit) {
            return $this->failure($transaction, 'unbalanced_double_entry_ledger');
        }

        if ((int) $debit->account_id !== (int) $transaction->from_account_id
            || (int) $debit->amount !== -((int) $transaction->amount)) {
            return $this->failure($transaction, 'debit_entry_does_not_match_transaction');
        }

        if ((int) $credit->account_id !== (int) $transaction->to_account_id
            || (int) $credit->amount !== (int) $transaction->amount) {
            return $this->failure($transaction, 'credit_entry_does_not_match_transaction');
        }

        return null;
    }

    private function auditDuplicateIdempotencyKeys()
    {
        return NajmTransaction::query()
            ->orderBy('id')
            ->get()
            ->map(function (NajmTransaction $transaction) {
                $key = data_get($transaction->metadata, 'idempotency_key');

                return is_string($key) && trim($key) !== ''
                    ? ['key' => trim($key), 'transaction_id' => (int) $transaction->id]
                    : null;
            })
            ->filter()
            ->groupBy('key')
            ->filter(fn ($rows) => $rows->count() > 1)
            ->map(fn ($rows, string $key) => [
                'idempotency_key' => $key,
                'transaction_ids' => $rows->pluck('transaction_id')->values()->all(),
                'issue' => 'duplicate_idempotency_key',
            ])
            ->values();
    }

    private function auditNonCompletedLedgerEffects()
    {
        return NajmTransaction::query()
            ->where('status', '!=', 'completed')
            ->whereIn('id', LedgerEntry::query()->select('transaction_id')->distinct())
            ->orderBy('id')
            ->get()
            ->map(fn (NajmTransaction $transaction) => [
                'transaction_id' => (int) $transaction->id,
                'tracking_number' => $transaction->tracking_number,
                'status' => $transaction->status,
                'ledger_entry_count' => LedgerEntry::where('transaction_id', $transaction->id)->count(),
                'issue' => 'non_completed_transaction_has_ledger_effects',
            ])
            ->values();
    }

    private function failure(NajmTransaction $transaction, string $issue): array
    {
        return [
            'transaction_id' => (int) $transaction->id,
            'tracking_number' => $transaction->tracking_number,
            'issue' => $issue,
        ];
    }
}
