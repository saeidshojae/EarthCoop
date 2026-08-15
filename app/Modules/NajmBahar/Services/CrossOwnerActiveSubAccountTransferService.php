<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Canonical executor for Active-Bahar transfers between sub-accounts owned by
 * different members. Dim is deliberately unsupported here.
 */
class CrossOwnerActiveSubAccountTransferService
{
    public function transfer(
        SubAccount $from,
        SubAccount $to,
        int $amount,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): NajmTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if ((int) $from->account_id === (int) $to->account_id) {
            throw new \RuntimeException('Cross-owner transfer requires independent owners.');
        }

        try {
            return DB::transaction(function () use ($from, $to, $amount, $description, $idempotencyKey, $meta) {
                if ($idempotencyKey) {
                    $existing = app(FinancialIdempotencyReplayService::class)->find($idempotencyKey);
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
                if ((int) $source->account_id === (int) $destination->account_id) {
                    throw new \RuntimeException('Cross-owner transfer requires independent owners.');
                }

                $accountService = app(AccountService::class);
                $sourceMirror = $accountService->ensureSubAccountAccount($source);
                $destinationMirror = $accountService->ensureSubAccountAccount($destination);

                $policy = app(TransactionService::class);
                $policy->assertEffectiveOwnerTransferAllowed($sourceMirror, $destinationMirror, $meta);

                $available = (int) ($source->balance_active ?? 0);
                if ($available < $amount) {
                    throw new \RuntimeException('Insufficient active funds in sub-account');
                }

                $source->balance_active = $available - $amount;
                $destination->balance_active = (int) ($destination->balance_active ?? 0) + $amount;
                $source->balance = (int) ($source->balance_active ?? 0) + (int) ($source->balance_faded ?? 0);
                $destination->balance = (int) ($destination->balance_active ?? 0) + (int) ($destination->balance_faded ?? 0);
                $source->save();
                $destination->save();

                $invariants = app(AccountInvariantService::class);
                $sourceCanonical = $invariants->reconcileSubAccountMirror($source->fresh());
                $destinationCanonical = $invariants->reconcileSubAccountMirror($destination->fresh());

                $metadata = array_merge($meta, [
                    'transfer_type' => 'subaccount',
                    'from_sub_account_id' => (int) $source->id,
                    'to_sub_account_id' => (int) $destination->id,
                    'from_sub_account_code' => (string) $source->sub_account_code,
                    'to_sub_account_code' => (string) $destination->sub_account_code,
                    'money_state' => 'active',
                    'balance_type' => 'active',
                    'routed_by' => 'safe_sub_account_service',
                    'executor' => 'cross_owner_active_sub_account_transfer_service',
                ]);
                if ($idempotencyKey) {
                    $metadata['idempotency_key'] = $idempotencyKey;
                }

                $transaction = NajmTransaction::create([
                    'from_account_id' => (int) $sourceCanonical['mirror_account_id'],
                    'to_account_id' => (int) $destinationCanonical['mirror_account_id'],
                    'amount' => $amount,
                    'type' => 'immediate',
                    'status' => 'completed',
                    'metadata' => $metadata,
                    'description' => $description,
                ]);

                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => (int) $sourceCanonical['mirror_account_id'],
                    'amount' => -$amount,
                    'entry_type' => 'debit',
                    'meta' => $metadata,
                ]);
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => (int) $destinationCanonical['mirror_account_id'],
                    'amount' => $amount,
                    'entry_type' => 'credit',
                    'meta' => $metadata,
                ]);

                return $transaction;
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey) {
                return app(FinancialIdempotencyReplayService::class)->replayOrThrow($exception, $idempotencyKey);
            }
            throw $exception;
        }
    }
}
