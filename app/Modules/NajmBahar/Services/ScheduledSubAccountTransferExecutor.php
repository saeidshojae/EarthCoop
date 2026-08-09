<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Canonical execution boundary for scheduled sub-account transfers.
 *
 * Scheduled transfers historically create a placeholder NajmTransaction before
 * execution. Release C preserves that transaction identity here, while removing
 * placeholder completion from the general SubAccountService mutation API.
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
            if (! in_array($moneyState, ['active', 'faded'], true)) {
                throw new \InvalidArgumentException('Money state must be active or faded.');
            }
            if ($fromSubAccountId === $toSubAccountId) {
                throw new \RuntimeException('Source and destination sub-accounts are the same.');
            }

            $ids = [$fromSubAccountId, $toSubAccountId];
            sort($ids, SORT_NUMERIC);
            $locked = [];
            foreach ($ids as $id) {
                $locked[$id] = SubAccount::query()->lockForUpdate()->findOrFail($id);
            }

            $from = $locked[$fromSubAccountId];
            $to = $locked[$toSubAccountId];
            if ((int) $from->status !== 1 || (int) $to->status !== 1) {
                throw new \RuntimeException('Sub-account is inactive.');
            }

            $sameOwner = (int) $from->account_id === (int) $to->account_id;
            if ($moneyState === 'faded' && ! $sameOwner) {
                throw new \RuntimeException('پول کمرنگ قابل انتقال بین اشخاص یا نهادهای مستقل نیست.');
            }

            $accounts = app(AccountService::class);
            $fromMirror = $accounts->ensureSubAccountAccount($from);
            $toMirror = $accounts->ensureSubAccountAccount($to);

            if (! $sameOwner && $moneyState === 'active') {
                /** @var SafeTransactionService $transactions */
                $transactions = app(TransactionService::class);
                $transactions->assertEffectiveOwnerTransferAllowed($fromMirror, $toMirror);
            }

            if ($moneyState === 'active') {
                if ((int) $from->balance_active < $amount) {
                    throw new \RuntimeException('Insufficient active funds in sub-account.');
                }
                $from->balance_active = (int) $from->balance_active - $amount;
                $to->balance_active = (int) $to->balance_active + $amount;
            } else {
                if ((int) $from->balance_faded < $amount) {
                    throw new \RuntimeException('Insufficient faded funds in sub-account.');
                }
                $from->balance_faded = (int) $from->balance_faded - $amount;
                $to->balance_faded = (int) $to->balance_faded + $amount;
            }

            $from->balance = (int) $from->balance_active + (int) $from->balance_faded;
            $to->balance = (int) $to->balance_active + (int) $to->balance_faded;
            $from->save();
            $to->save();

            $invariants = app(AccountInvariantService::class);
            $fromMirror = $invariants->reconcileSubAccountMirror($from->fresh());
            $toMirror = $invariants->reconcileSubAccountMirror($to->fresh());

            $meta = array_merge((array) ($transaction->metadata ?? []), [
                'transfer_type' => 'subaccount',
                'from_sub_account_id' => (int) $from->id,
                'to_sub_account_id' => (int) $to->id,
                'from_sub_account_code' => (string) $from->sub_account_code,
                'to_sub_account_code' => (string) $to->sub_account_code,
                'money_state' => $moneyState,
                'scheduled_transaction_id' => (int) $lockedScheduled->id,
                'executed_by' => 'scheduled_sub_account_transfer_executor',
            ]);

            $transaction->from_account_id = $fromMirror->id;
            $transaction->to_account_id = $toMirror->id;
            $transaction->amount = $amount;
            $transaction->type = 'scheduled';
            $transaction->status = 'completed';
            $transaction->metadata = $meta;
            if ($description) {
                $transaction->description = $description;
            }
            $transaction->save();

            if (LedgerEntry::where('transaction_id', $transaction->id)->exists()) {
                throw new \RuntimeException('Pending scheduled transfer already has ledger entries.');
            }

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $fromMirror->id,
                'amount' => -$amount,
                'entry_type' => 'debit',
                'meta' => $meta,
            ]);
            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $toMirror->id,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => $meta,
            ]);

            return $transaction->fresh();
        });
    }
}
