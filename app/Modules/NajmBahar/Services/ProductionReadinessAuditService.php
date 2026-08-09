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

        $ledgerFailures = NajmTransaction::query()
            ->where('status', 'completed')
            ->orderBy('id')
            ->get()
            ->map(fn (NajmTransaction $transaction) => $this->auditCompletedTransaction($transaction))
            ->filter()
            ->values();

        return [
            'ok' => $accountFailures->isEmpty() && $ledgerFailures->isEmpty(),
            'accounts_checked' => $accountAudits->count(),
            'account_failures' => $accountFailures->all(),
            'completed_transactions_checked' => NajmTransaction::where('status', 'completed')->count(),
            'ledger_failures' => $ledgerFailures->all(),
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

        if ($fromExternal xor $toExternal) {
            if ($entries->count() !== 1) {
                return $this->failure($transaction, 'external_monetary_event_must_have_one_entry');
            }

            $entry = $entries->first();
            $expected = $fromExternal ? (int) $transaction->amount : -((int) $transaction->amount);
            if ((int) $entry->amount !== $expected) {
                return $this->failure($transaction, 'external_monetary_event_amount_mismatch');
            }

            return null;
        }

        $sum = (int) $entries->sum(fn (LedgerEntry $entry) => (int) $entry->amount);
        $hasDebit = $entries->contains(fn (LedgerEntry $entry) => $entry->entry_type === 'debit');
        $hasCredit = $entries->contains(fn (LedgerEntry $entry) => $entry->entry_type === 'credit');

        if ($entries->count() < 2 || $sum !== 0 || ! $hasDebit || ! $hasCredit) {
            return $this->failure($transaction, 'unbalanced_double_entry_ledger');
        }

        return null;
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
