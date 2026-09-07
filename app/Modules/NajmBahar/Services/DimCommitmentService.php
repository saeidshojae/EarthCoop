<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DimCommitmentService
{
    public function commit(
        Account $account,
        int $amount,
        string $description,
        string $idempotencyKey,
        array $meta = []
    ): Transaction {
        return $this->move($account, $amount, true, $description, $idempotencyKey, $meta);
    }

    public function release(
        Account $account,
        int $amount,
        string $description,
        string $idempotencyKey,
        array $meta = []
    ): Transaction {
        return $this->move($account, $amount, false, $description, $idempotencyKey, $meta);
    }

    /**
     * Consume committed Dim and settle it as Active Bahar in an execution account.
     *
     * This is the monetary boundary used after governance has emitted an execution
     * authorization. Governance never calls this mutation directly.
     */
    public function settleCommittedToActive(
        Account $source,
        Account $destination,
        int $amount,
        string $description,
        string $idempotencyKey,
        array $meta = []
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Committed Dim settlement amount must be positive.');
        }
        if ((int) $source->id === (int) $destination->id) {
            throw new \InvalidArgumentException('Committed Dim settlement requires a distinct destination account.');
        }

        return DB::transaction(function () use ($source, $destination, $amount, $description, $idempotencyKey, $meta) {
            $existing = Transaction::where('metadata->idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $ids = [(int) $source->id, (int) $destination->id];
            sort($ids, SORT_NUMERIC);
            $lockedAccounts = Account::whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedSource = $lockedAccounts->get((int) $source->id);
            $lockedDestination = $lockedAccounts->get((int) $destination->id);
            if (! $lockedSource || ! $lockedDestination) {
                throw new \RuntimeException('Settlement account not found.');
            }

            $committedDim = (int) ($lockedSource->committed_dim ?? 0);
            if ($committedDim < $amount) {
                throw new \RuntimeException('Insufficient committed Dim for execution settlement.');
            }

            $lockedSource->committed_dim = $committedDim - $amount;
            $lockedSource->balance = (int) ($lockedSource->balance_active ?? 0)
                + (int) ($lockedSource->balance_faded ?? 0)
                + (int) ($lockedSource->committed_dim ?? 0);
            $lockedSource->save();

            $lockedDestination->balance_active = (int) ($lockedDestination->balance_active ?? 0) + $amount;
            $lockedDestination->balance = (int) ($lockedDestination->balance_active ?? 0)
                + (int) ($lockedDestination->balance_faded ?? 0)
                + (int) ($lockedDestination->committed_dim ?? 0);
            $lockedDestination->save();

            $eventMeta = array_merge($meta, [
                'type' => 'public_execution_settlement',
                'event' => 'DIM_COMMITTED_SETTLED_AS_ACTIVE',
                'monetary_event' => 'money_activated_and_transferred',
                'idempotency_key' => $idempotencyKey,
                'source_account_id' => (int) $lockedSource->id,
                'destination_account_id' => (int) $lockedDestination->id,
                'from_balance_bucket' => 'dim_committed',
                'to_balance_bucket' => 'active',
                'amount_gol' => $amount,
                'system_operation' => true,
            ]);

            $transaction = Transaction::create([
                'from_account_id' => $lockedSource->id,
                'to_account_id' => $lockedDestination->id,
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => $eventMeta,
                'description' => $description,
            ]);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $lockedSource->id,
                'amount' => -$amount,
                'entry_type' => 'debit',
                'meta' => array_merge($eventMeta, ['balance_bucket' => 'dim_committed']),
            ]);
            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $lockedDestination->id,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => array_merge($eventMeta, ['balance_bucket' => 'active']),
            ]);

            return $transaction;
        }, 3);
    }

    private function move(
        Account $account,
        int $amount,
        bool $toCommitted,
        string $description,
        string $idempotencyKey,
        array $meta
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Dim commitment amount must be positive.');
        }

        return DB::transaction(function () use ($account, $amount, $toCommitted, $description, $idempotencyKey, $meta) {
            $existing = Transaction::where('metadata->idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            // The transitional Release-C model stores committed Dim only on main/legal
            // accounts. Sub-account mirrors are reconciled from SubAccount and reset
            // committed_dim to zero, so accepting a new commitment here would create
            // state that a later reconciliation silently erases. Reject only new
            // commitments; releases remain available for recovery of any legacy drift.
            if ($toCommitted && $locked->type === 'subaccount') {
                throw new \RuntimeException('Dim commitment is not supported on sub-account mirrors.');
            }

            $availableDim = (int) ($locked->balance_faded ?? 0);
            $committedDim = (int) ($locked->committed_dim ?? 0);

            if ($toCommitted && $availableDim < $amount) {
                throw new \RuntimeException('Insufficient available Dim for commitment.');
            }
            if (! $toCommitted && $committedDim < $amount) {
                throw new \RuntimeException('Insufficient committed Dim for release.');
            }

            if ($toCommitted) {
                $locked->balance_faded = $availableDim - $amount;
                $locked->committed_dim = $committedDim + $amount;
            } else {
                $locked->balance_faded = $availableDim + $amount;
                $locked->committed_dim = $committedDim - $amount;
            }

            $locked->balance = (int) ($locked->balance_active ?? 0)
                + (int) ($locked->balance_faded ?? 0)
                + (int) ($locked->committed_dim ?? 0);
            $locked->save();

            $event = $toCommitted ? 'DIM_COMMITTED' : 'DIM_RELEASED';
            $eventMeta = array_merge($meta, [
                'type' => 'dim_commitment',
                'event' => $event,
                'money_state' => 'dim',
                'idempotency_key' => $idempotencyKey,
                'account_id' => (int) $locked->id,
            ]);

            $transaction = Transaction::create([
                'from_account_id' => $locked->id,
                'to_account_id' => $locked->id,
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => $eventMeta,
                'description' => $description,
            ]);

            $fromBucket = $toCommitted ? 'dim_available' : 'dim_committed';
            $toBucket = $toCommitted ? 'dim_committed' : 'dim_available';

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $locked->id,
                'amount' => -$amount,
                'entry_type' => 'debit',
                'meta' => array_merge($eventMeta, ['balance_bucket' => $fromBucket]),
            ]);
            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $locked->id,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => array_merge($eventMeta, ['balance_bucket' => $toBucket]),
            ]);

            return $transaction;
        }, 3);
    }
}