<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use Illuminate\Support\Facades\DB;

class MonetaryService
{
    public function issueMembershipCredit(Account $account, int $userId): array
    {
        $idempotencyKey = 'membership-issuance-' . $userId;

        return DB::transaction(function () use ($account, $userId, $idempotencyKey) {
            if ($existing = $this->findExistingEvent($idempotencyKey)) {
                return ['transaction' => $existing, 'amount' => (int) $existing->amount, 'applied' => false];
            }

            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($locked->type !== 'user' || (int) $locked->user_id !== $userId) {
                throw new \RuntimeException('Membership issuance requires an account owned by the member.');
            }

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

            $transaction = app(MonetaryEventRecorder::class)->creditFromSystem(
                $locked,
                $amount,
                'faded',
                'صدور اعتبار اولیه عضویت نجم بهار - ۱۰٬۰۰۰ بهار کمرنگ',
                $metadata
            );

            return ['transaction' => $transaction, 'amount' => $amount, 'applied' => true];
        });
    }

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

        return DB::transaction(function () use ($account, $requestedAmount, $reason, $metadata, $idempotencyKey, $allowPartial) {
            if ($existing = $this->findExistingEvent($idempotencyKey)) {
                return ['transaction' => $existing, 'amount' => (int) $existing->amount, 'applied' => false];
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
            $locked->balance = (int) $locked->balance_faded
                + (int) $locked->balance_active
                + (int) ($locked->committed_dim ?? 0);
            $locked->save();
            $this->syncSubAccountMirror($locked);

            $eventMetadata = array_merge($metadata, [
                'type' => $metadata['type'] ?? 'money_activation',
                'monetary_event' => 'money_activated',
                'activation_reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'from_balance_type' => 'faded',
                'to_balance_type' => 'active',
                'amount_gol' => $amount,
            ]);

            $transaction = app(MonetaryEventRecorder::class)->convertBucket(
                $locked,
                $amount,
                'faded',
                'active',
                $reason,
                $eventMetadata
            );

            return ['transaction' => $transaction, 'amount' => $amount, 'applied' => true];
        });
    }

    /** Cancel dim money from an account without touching active wealth. */
    public function cancelDim(
        Account $account,
        int $requestedAmount,
        string $reason,
        array $metadata,
        string $idempotencyKey,
        bool $allowPartial = true
    ): array {
        if ($requestedAmount <= 0) {
            throw new \InvalidArgumentException('Cancellation amount must be positive.');
        }

        return DB::transaction(function () use ($account, $requestedAmount, $reason, $metadata, $idempotencyKey, $allowPartial) {
            if ($existing = $this->findExistingEvent($idempotencyKey)) {
                return ['transaction' => $existing, 'amount' => (int) $existing->amount, 'applied' => false];
            }

            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $available = (int) ($locked->balance_faded ?? 0);
            if ($available <= 0) {
                return ['transaction' => null, 'amount' => 0, 'applied' => false];
            }
            if (! $allowPartial && $available < $requestedAmount) {
                throw new \RuntimeException('Insufficient dim funds for cancellation.');
            }

            $amount = $allowPartial ? min($requestedAmount, $available) : $requestedAmount;
            $locked->balance_faded = $available - $amount;
            $locked->balance = (int) ($locked->balance_active ?? 0) + (int) $locked->balance_faded;
            $locked->save();
            $this->syncSubAccountMirror($locked);

            $eventMetadata = array_merge($metadata, [
                'type' => $metadata['type'] ?? 'money_cancellation',
                'monetary_event' => 'money_cancelled',
                'idempotency_key' => $idempotencyKey,
                'balance_bucket' => 'faded',
                'amount_gol' => $amount,
                'system_operation' => true,
            ]);

            $transaction = app(MonetaryEventRecorder::class)->debitToSystem(
                $locked,
                $amount,
                'faded',
                $reason,
                $eventMetadata
            );

            return ['transaction' => $transaction, 'amount' => $amount, 'applied' => true];
        });
    }

    /** Destroy active money held by a system fund. */
    public function destroyActive(
        Account $account,
        int $requestedAmount,
        string $reason,
        array $metadata,
        string $idempotencyKey,
        bool $allowPartial = true
    ): array {
        if ($requestedAmount <= 0) {
            throw new \InvalidArgumentException('Destruction amount must be positive.');
        }

        return DB::transaction(function () use ($account, $requestedAmount, $reason, $metadata, $idempotencyKey, $allowPartial) {
            if ($existing = $this->findExistingEvent($idempotencyKey)) {
                return ['transaction' => $existing, 'amount' => (int) $existing->amount, 'applied' => false];
            }

            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $available = (int) ($locked->balance_active ?? 0);
            if ($available <= 0) {
                return ['transaction' => null, 'amount' => 0, 'applied' => false];
            }
            if (! $allowPartial && $available < $requestedAmount) {
                throw new \RuntimeException('Insufficient active funds for destruction.');
            }

            $amount = $allowPartial ? min($requestedAmount, $available) : $requestedAmount;
            $locked->balance_active = $available - $amount;
            $locked->balance = (int) $locked->balance_active + (int) ($locked->balance_faded ?? 0);
            $locked->save();
            $this->syncSubAccountMirror($locked);

            $eventMetadata = array_merge($metadata, [
                'type' => $metadata['type'] ?? 'money_destruction',
                'monetary_event' => 'money_destroyed',
                'idempotency_key' => $idempotencyKey,
                'balance_bucket' => 'active',
                'amount_gol' => $amount,
                'system_operation' => true,
            ]);

            $transaction = app(MonetaryEventRecorder::class)->debitToSystem(
                $locked,
                $amount,
                'active',
                $reason,
                $eventMetadata
            );

            return ['transaction' => $transaction, 'amount' => $amount, 'applied' => true];
        });
    }

    public function repairLegacyUnbucketedBalance(Account $account): bool
    {
        return DB::transaction(function () use ($account) {
            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->balance <= 0
                || (int) ($locked->balance_active ?? 0) !== 0
                || (int) ($locked->balance_faded ?? 0) !== 0) {
                return false;
            }

            $amount = (int) $locked->balance;
            $locked->balance_active = 0;
            $locked->balance_faded = $amount;
            $locked->save();

            $metadata = [
                'type' => 'legacy_balance_classification',
                'monetary_event' => 'historical_balance_classified',
                'amount_gol' => $amount,
                'to_balance_type' => 'faded',
                'system_operation' => true,
            ];

            $transaction = NajmTransaction::create([
                'from_account_id' => $locked->id,
                'to_account_id' => $locked->id,
                'amount' => $amount,
                'type' => 'adjustment',
                'status' => 'completed',
                'metadata' => $metadata,
                'description' => 'طبقه‌بندی موجودی legacy بدون bucket به‌عنوان پول کمرنگ',
            ]);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $locked->id,
                'amount' => 0,
                'entry_type' => 'credit',
                'meta' => array_merge($metadata, [
                    'balance_bucket' => 'faded',
                    'classification_only' => true,
                ]),
            ]);

            return true;
        });
    }

    private function findExistingEvent(string $idempotencyKey): ?NajmTransaction
    {
        return NajmTransaction::query()
            ->where('metadata->idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function syncSubAccountMirror(Account $account): void
    {
        app(AccountInvariantService::class)->reconcileSubAccountFromMirror($account);
    }
}
