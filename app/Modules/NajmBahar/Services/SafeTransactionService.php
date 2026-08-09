<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;

/**
 * Transitional adapter around TransactionService.
 *
 * Existing non-internal transfers keep the mature locking/idempotency behavior
 * in TransactionService. Own Main ↔ Sub movements are routed to the canonical
 * internal service so Account.balance remains local and money state is never
 * changed as a side effect of moving between a member's own accounts.
 */
class SafeTransactionService extends TransactionService
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
        if ($fromAccountNumber
            && in_array($balanceType, ['active', 'faded'], true)
            && $amount > 0) {
            $from = Account::where('account_number', $fromAccountNumber)->first();
            $to = Account::where('account_number', $toAccountNumber)->first();

            if ($from && $to) {
                $internal = $this->resolveInternalOwnTransfer($from, $to);
                if ($internal) {
                    [$direction, $main, $sub] = $internal;
                    $key = $idempotencyKey ?? implode('-', [
                        'safe-internal-transfer',
                        $direction,
                        $main->id,
                        $sub->id,
                        $balanceType,
                        $amount,
                        now()->format('YmdHisv'),
                    ]);

                    $internalService = app(InternalAccountTransferService::class);
                    $metadata = array_merge($meta, [
                        'requested_transaction_type' => $transactionType,
                        'routed_by' => 'safe_transaction_service',
                    ]);

                    if ($direction === 'main_to_sub') {
                        return $internalService->mainToSub(
                            $main,
                            $sub,
                            $amount,
                            $balanceType,
                            $description ?? 'انتقال داخلی به حساب فرعی',
                            $key,
                            $metadata
                        );
                    }

                    return $internalService->subToMain(
                        $sub,
                        $main,
                        $amount,
                        $balanceType,
                        $description ?? 'انتقال داخلی به حساب اصلی',
                        $key,
                        $metadata
                    );
                }
            }
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

    private function resolveInternalOwnTransfer(Account $from, Account $to): ?array
    {
        if ($from->type !== 'subaccount' && $to->type === 'subaccount') {
            $sub = SubAccount::where('sub_account_code', $to->account_number)->first();
            if ($sub && (int) $sub->account_id === (int) $from->id) {
                return ['main_to_sub', $from, $sub];
            }
        }

        if ($from->type === 'subaccount' && $to->type !== 'subaccount') {
            $sub = SubAccount::where('sub_account_code', $from->account_number)->first();
            if ($sub && (int) $sub->account_id === (int) $to->id) {
                return ['sub_to_main', $to, $sub];
            }
        }

        return null;
    }
}
