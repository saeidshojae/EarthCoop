<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use Illuminate\Support\Facades\DB;

class MonetaryService
{
    /**
     * Issue the one-time constitutional membership credit.
     *
     * The issuance is always exactly 10,000 Bahar and always enters the
     * economy as dim money. Replays are idempotent and do not mint twice.
     */
    public function issueMembershipCredit(Account $account, int $userId): array
    {
        $idempotencyKey = 'membership-issuance-' . $userId;

        return DB::transaction(function () use ($account, $userId, $idempotencyKey) {
            $existing = NajmTransaction::where('metadata->idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'transaction' => $existing,
                    'amount' => (int) $existing->amount,
                    'applied' => false,
                ];
            }

            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $amount = NajmBaharConstitution::initialMembershipGol();

            if ((int) $locked->balance !== 0
                || (int) ($locked->balance_active ?? 0) !== 0
                || (int) ($locked->balance_faded ?? 0) !== 0) {
                throw new \RuntimeException('Membership issuance requires a zero-balance account.');
            }

            $locked->balance_active = 0;
            $locked->balance_faded = $amount;
            $locked->balance = $amount;
            $locked->save();

            $metadata = [
                'type' => 'initial_funding',
                'monetary_event' => 'money_created',
                'issuance_reason' => 'membership',
                'user_id' => $userId,
                'system_operation' => true,
                'idempotency_key' => $idempotencyKey,
                'active_amount' => 0,
                'faded_amount' => $amount,
                'to_balance_type' => 'faded',
                'constitution_version' => NajmBaharConstitution::VERSION,
            ];

            $transaction = NajmTransaction::create([
                'from_account_id' => null,
                'to_account_id' => $locked->id,
                'amount' => $amount,
                'type' => 'adjustment',
                'status' => 'completed',
                'metadata' => $metadata,
                'description' => 'صدور اعتبار اولیه عضویت نجم بهار - ۱۰٬۰۰۰ بهار کمرنگ',
            ]);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $locked->id,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => array_merge($metadata, [
                    'balance_bucket' => 'faded',
                ]),
            ]);

            return [
                'transaction' => $transaction,
                'amount' => $amount,
                'applied' => true,
            ];
        });
    }

    /**
     * Activate existing dim money without changing total monetary supply.
     *
     * @return array{transaction:NajmTransaction, amount:int, applied:bool}
     */
    public function activateDim(
        Account $account,
        int $requestedAmount,
        string $reason,
        array $metadata,
        string $idempotencyKey,
        bool $allowPartial = false
    ): array {
        if ($requestedAmount <= 0) {
            throw new \InvalidArgumentException('Activation amount must be positive.');
        }

        return DB::transaction(function () use (
            $account,
            $requestedAmount,
            $reason,
            $metadata,
            $idempotencyKey,
            $allowPartial
        ) {
            $existing = NajmTransaction::where('metadata->idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'transaction' => $existing,
                    'amount' => (int) $existing->amount,
                    'applied' => false,
                ];
            }

            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $available = (int) ($locked->balance_faded ?? 0);

            if ($available <= 0) {
                throw new \RuntimeException('No dim balance is available for activation.');
            }

            if (! $allowPartial && $available < $requestedAmount) {
                throw new \RuntimeException('Insufficient dim funds for activation.');
            }

            $amount = $allowPartial ? min($requestedAmount, $available) : $requestedAmount;

            $locked->balance_faded = $available - $amount;
            $locked->balance_active = (int) ($locked->balance_active ?? 0) + $amount;
            $locked->balance = (int) $locked->balance_faded + (int) $locked->balance_active;
            $locked->save();

            $eventMetadata = array_merge($metadata, [
                'type' => $metadata['type'] ?? 'money_activation',
                'monetary_event' => 'money_activated',
                'activation_reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'from_balance_type' => 'faded',
                'to_balance_type' => 'active',
                'amount_gol' => $amount,
            ]);

            $transaction = NajmTransaction::create([
                'from_account_id' => $locked->id,
                'to_account_id' => $locked->id,
                'amount' => $amount,
                'type' => 'adjustment',
                'status' => 'completed',
                'metadata' => $eventMetadata,
                'description' => $reason,
            ]);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $locked->id,
                'amount' => -$amount,
                'entry_type' => 'debit',
                'meta' => array_merge($eventMetadata, [
                    'balance_bucket' => 'faded',
                ]),
            ]);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $locked->id,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => array_merge($eventMetadata, [
                    'balance_bucket' => 'active',
                ]),
            ]);

            return [
                'transaction' => $transaction,
                'amount' => $amount,
                'applied' => true,
            ];
        });
    }
}
