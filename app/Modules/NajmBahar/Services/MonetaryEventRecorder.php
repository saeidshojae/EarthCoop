<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;

/**
 * Records already-authorized monetary events after the caller has mutated and
 * locked balances inside its surrounding database transaction.
 *
 * This service never decides whether money may be created, activated,
 * cancelled or destroyed. MonetaryService remains the policy/mutation owner.
 */
class MonetaryEventRecorder
{
    public function creditFromSystem(
        Account $account,
        int $amount,
        string $bucket,
        string $description,
        array $metadata
    ): NajmTransaction {
        $transaction = $this->createTransaction(null, $account->id, $amount, $description, $metadata);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'entry_type' => 'credit',
            'meta' => array_merge($metadata, ['balance_bucket' => $bucket]),
        ]);

        return $transaction;
    }

    public function debitToSystem(
        Account $account,
        int $amount,
        string $bucket,
        string $description,
        array $metadata
    ): NajmTransaction {
        $transaction = $this->createTransaction($account->id, null, $amount, $description, $metadata);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $account->id,
            'amount' => -$amount,
            'entry_type' => 'debit',
            'meta' => array_merge($metadata, ['balance_bucket' => $bucket]),
        ]);

        return $transaction;
    }

    public function convertBucket(
        Account $account,
        int $amount,
        string $fromBucket,
        string $toBucket,
        string $description,
        array $metadata
    ): NajmTransaction {
        $transaction = $this->createTransaction($account->id, $account->id, $amount, $description, $metadata);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $account->id,
            'amount' => -$amount,
            'entry_type' => 'debit',
            'meta' => array_merge($metadata, ['balance_bucket' => $fromBucket]),
        ]);
        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'entry_type' => 'credit',
            'meta' => array_merge($metadata, ['balance_bucket' => $toBucket]),
        ]);

        return $transaction;
    }

    private function createTransaction(
        ?int $fromAccountId,
        ?int $toAccountId,
        int $amount,
        string $description,
        array $metadata
    ): NajmTransaction {
        return NajmTransaction::create([
            'from_account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'amount' => $amount,
            'type' => 'adjustment',
            'status' => 'completed',
            'metadata' => $metadata,
            'description' => $description,
        ]);
    }
}
