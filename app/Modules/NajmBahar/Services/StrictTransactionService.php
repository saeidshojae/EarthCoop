<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;

/**
 * Release D final guard around the transitional transaction adapter.
 *
 * Main economic actors must not fall through to the legacy generic balance
 * mutation path. Explicit internal-account, child-account and system flows are
 * still delegated to SafeTransactionService and its canonical executors.
 */
class StrictTransactionService extends SafeTransactionService
{
    public function transfer(
        string|null $fromAccountNumber,
        string $toAccountNumber,
        int $amount,
        string $description = null,
        array $meta = [],
        string|null $idempotencyKey = null,
        string $balanceType = 'balance',
        ?string $transactionType = null
    ): NajmTransaction {
        $from = $fromAccountNumber
            ? Account::where('account_number', $fromAccountNumber)->first()
            : null;
        $to = Account::where('account_number', $toAccountNumber)->first();

        if ($from
            && $to
            && $from->type !== 'system'
            && $to->type !== 'system'
            && $from->type !== 'subaccount'
            && $to->type !== 'subaccount') {
            throw new \RuntimeException(
                'Direct economic-actor transfers must use an explicit canonical transfer boundary.'
            );
        }

        return parent::transfer(
            $fromAccountNumber,
            $toAccountNumber,
            $amount,
            $description,
            $meta,
            $idempotencyKey,
            $balanceType,
            $transactionType
        );
    }
}
