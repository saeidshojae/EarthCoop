<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Canonical execution boundary for scheduled sub-account transfers.
 *
 * Scheduled transfers historically create a placeholder NajmTransaction before
 * execution. This executor preserves that transaction identity while making
 * replay idempotent: a completed placeholder with its double-entry ledger is
 * returned without moving money again.
 */
class ScheduledSubAccountTransferExecutor
{
    public function execute(ScheduledTransaction $scheduled): NajmTransaction
    {
        return DB::transaction(function () use ($scheduled) {
            $lockedScheduled = ScheduledTransaction::query()
                ->lockForUpdate()
                ->findOrFail($scheduled->id);

            if (! $lockedScheduled->transaction_id) {
                throw new \RuntimeException('Scheduled sub-account transfer has no placeholder transaction.');
            }

            $transaction = NajmTransaction::query()
                ->lockForUpdate()
                ->findOrFail($lockedScheduled->transaction_id);

            if ($transaction->status === 'completed') {
                if (LedgerEntry::where('transaction_id', $transaction->id)->count() !== 2) {
                    throw new \RuntimeException('Completed scheduled transfer is missing its canonical double-entry ledger.');
                }

                return $transaction;
            }

            if (! in_array($transaction->status, ['pending', 'failed'], true)) {
                throw new \RuntimeException('Scheduled transfer placeholder is not executable.');
            }

            $payload = (array) ($lockedScheduled->payload ?? []);
            $fromSubAccountId = isset($payload['from_sub_account_id']) ? (int) $payload['from_sub_account_id'] : 0;
            $toSubAccountId = isset($payload['to_sub_account_id']) ? (int) $payload['to_sub_account_id'] : 0;
            $amount = isset($payload['amount']) ? (int) $payload['amount'] : 0;
            $description = $payload['description'] ?? null;
            $moneyState = $payload['money_state'] ?? ($payload['metadata']['money_state'] ?? 'faded');

            if ($fromSubAccountId <= 0 || $toSubAccountId <= 0 || $amount <= 0) {
                throw new \RuntimeException('Invalid scheduled sub-account payload.');
            }

            $result = app(SubAccountService::class)->transferBetweenSubAccounts(
                $fromSubAccountId,
                $toSubAccountId,
                $amount,
                $description,
                $moneyState,
                (int) $transaction->id
            );

            if (! $result || (int) $result->id !== (int) $transaction->id) {
                throw new \RuntimeException('Scheduled execution did not preserve placeholder transaction identity.');
            }

            if (LedgerEntry::where('transaction_id', $transaction->id)->count() !== 2) {
                throw new \RuntimeException('Scheduled execution did not produce exactly two ledger entries.');
            }

            return $result->fresh();
        });
    }
}
