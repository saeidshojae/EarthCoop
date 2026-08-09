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
