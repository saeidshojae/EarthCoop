<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;

/**
 * Transitional adapter around TransactionService.
 *
 * Existing active transfers keep the mature locking/idempotency behavior in
 * TransactionService. Own Main ↔ Sub movements are routed to the canonical
 * internal service so Account.balance remains local and money state is never
 * changed as a side effect of moving between a member's own accounts.
 *
 * Dim money is not spendable/transferable between economic actors. It may only
 * move between the same owner's accounts or be consumed by explicit monetary
 * operations such as activation, membership payment, or retirement.
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

        if ($balanceType === 'faded') {
            // Legacy onboarding used to transfer 10 dim Bahar from the new member
            // to the referrer. The constitutional model awards participation
            // points instead; suppress the monetary transfer while retaining an
            // auditable record until the legacy controller is fully removed.
            if (($meta['type'] ?? null) === 'referral_bonus') {
                $key = $idempotencyKey ?? 'suppressed-referral-dim-' . sha1((string) $fromAccountNumber . '|' . $toAccountNumber . '|' . $amount);
                $existing = NajmTransaction::where('metadata->idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }

                return NajmTransaction::create([
                    'from_account_id' => Account::where('account_number', $fromAccountNumber)->value('id'),
                    'to_account_id' => Account::where('account_number', $toAccountNumber)->value('id'),
                    'amount' => 0,
                    'type' => 'adjustment',
                    'status' => 'completed',
                    'metadata' => array_merge($meta, [
                        'idempotency_key' => $key,
                        'monetary_event' => 'legacy_dim_transfer_suppressed',
                        'requested_amount_gol' => $amount,
                        'replacement_model' => 'participation_points_then_activate_own_dim',
                    ]),
                    'description' => 'مسیر قدیمی پاداش معرفی بدون انتقال پول کمرنگ متوقف شد',
                ]);
            }

            throw new \RuntimeException('پول کمرنگ قابل انتقال بین اشخاص یا نهادهای مستقل نیست. ابتدا باید از یک مسیر مجاز فعال شود.');
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
