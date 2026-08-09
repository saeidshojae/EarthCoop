<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Canonical executor for same-owner SubAccount ↔ SubAccount transfers.
 *
 * This service owns locking, balance mutation, mirror reconciliation and
 * double-entry transaction creation. It deliberately accepts no existing
 * transaction ID; scheduled placeholder completion belongs exclusively to
 * ScheduledSubAccountTransferExecutor.
 */
class InternalSubAccountTransferService
{
    public function transfer(
        SubAccount $from,
        SubAccount $to,
        int $amount,
        string $moneyState,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): NajmTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (! in_array($moneyState, ['active', 'faded'], true)) {
            throw new \InvalidArgumentException('Money state must be active or faded');
        }

        if ((int) $from->account_id !== (int) $to->account_id) {
            throw new \RuntimeException('Internal sub-account transfer requires the same owner.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $moneyState, $description, $idempotencyKey, $meta) {
            if ($idempotencyKey) {
                $existing = NajmTransaction::where('metadata->idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $ids = [(int) $from->id, (int) $to->id];
            sort($ids, SORT_NUMERIC);
            $locked = [];
            foreach ($ids as $id) {
                $locked[$id] = SubAccount::query()->lockForUpdate()->findOrFail($id);
            }

            $source = $locked[(int) $from->id];
            $destination = $locked[(int) $to->id];

            if ((int) $source->id === (int) $destination->id) {
                throw new \RuntimeException('Source and destination sub-accounts are the same');
            }

            if ((int) $source->status !== 1 || (int) $destination->status !== 1) {
                throw new \RuntimeException('Sub-account is inactive');
            }

            if ($moneyState === 'active') {
                if ((int) ($source->balance_active ?? 0) < $amount) {
                    throw new \RuntimeException('Insufficient active funds in sub-account');
                }
                $source->balance_active = (int) ($source->balance_active ?? 0) - $amount;
                $destination->balance_active = (int) ($destination->balance_active ?? 0) + $amount;
            } else {
                if ((int) ($source->balance_faded ?? 0) < $amount) {
                    throw new \RuntimeException('Insufficient faded funds in sub-account');
                }
                $source->balance_faded = (int) ($source->balance_faded ?? 0) - $amount;
                $destination->balance_faded = (int) ($destination->balance_faded ?? 0) + $amount;
            }

            $source->balance = (int) ($source->balance_active ?? 0) + (int) ($source->balance_faded ?? 0);
            $destination->balance = (int) ($destination->balance_active ?? 0) + (int) ($destination->balance_faded ?? 0);
            $source->save();
            $destination->save();

            $invariants = app(AccountInvariantService::class);
            $sourceMirror = $invariants->reconcileSubAccountMirror($source->fresh());
            $destinationMirror = $invariants->reconcileSubAccountMirror($destination->fresh());

            $metadata = array_merge($meta, [
                'transfer_type' => 'subaccount',
                'from_sub_account_id' => (int) $source->id,
                'to_sub_account_id' => (int) $destination->id,
                'from_sub_account_code' => (string) $source->sub_account_code,
                'to_sub_account_code' => (string) $destination->sub_account_code,
                'money_state' => $moneyState,
                'balance_type' => $moneyState,
                'routed_by' => 'internal_sub_account_transfer_service',
            ]);
            if ($idempotencyKey) {
                $metadata['idempotency_key'] = $idempotencyKey;
            }

            $transaction = NajmTransaction::create([
                'from_account_id' => (int) $sourceMirror['mirror_account_id'],
                'to_account_id' => (int) $destinationMirror['mirror_account_id'],
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => $metadata,
                'description' => $description,
            ]);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => (int) $sourceMirror['mirror_account_id'],
                'amount' => -$amount,
                'entry_type' => 'debit',
                'meta' => $metadata,
            ]);
            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => (int) $destinationMirror['mirror_account_id'],
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => $metadata,
            ]);

            return $transaction;
        });
    }
}
