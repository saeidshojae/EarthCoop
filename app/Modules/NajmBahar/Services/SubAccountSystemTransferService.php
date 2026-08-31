<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Canonical boundary for Active money leaving a member sub-account to system. */
class SubAccountSystemTransferService
{
    public function transfer(SubAccount $source, Account $systemAccount, int $amount, ?string $description = null, array $metadata = [], ?string $idempotencyKey = null, ?string $transactionType = null): NajmTransaction
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');
        if ($systemAccount->type !== 'system') throw new \RuntimeException('Destination must be a Najm Bahar system account.');

        try {
            return DB::transaction(function () use ($source, $systemAccount, $amount, $description, $metadata, $idempotencyKey, $transactionType) {
                if ($idempotencyKey && ($existing = app(FinancialIdempotencyReplayService::class)->find($idempotencyKey))) return $existing;

                $lockedSource = SubAccount::query()->lockForUpdate()->findOrFail($source->id);
                if ((int) $lockedSource->status !== 1) throw new \RuntimeException('Sub-account is inactive');

                $sourceMirror = Account::query()->where('account_number', $lockedSource->sub_account_code)->where('type','subaccount')->lockForUpdate()->firstOrFail();
                $lockedSystem = Account::query()->lockForUpdate()->findOrFail($systemAccount->id);
                $parent = Account::query()->lockForUpdate()->findOrFail($lockedSource->account_id);

                $active = (int) ($lockedSource->balance_active ?? 0);
                $availableActive = app(ActiveBaharReservationService::class)->availableActive($sourceMirror);
                if ($active < $amount || $availableActive < $amount) throw new \RuntimeException('Insufficient active funds in sub-account');

                $lockedSource->balance_active = $active - $amount;
                $lockedSource->balance = (int) $lockedSource->balance_active + (int) ($lockedSource->balance_faded ?? 0);
                $lockedSource->save();
                app(AccountInvariantService::class)->reconcileSubAccountMirror($lockedSource->fresh());
                $sourceMirror->refresh();

                $lockedSystem->balance_active = (int) ($lockedSystem->balance_active ?? 0) + $amount;
                $lockedSystem->balance = (int) ($lockedSystem->balance_active ?? 0) + (int) ($lockedSystem->balance_faded ?? 0);
                $lockedSystem->save();

                $parent->balance = (int) ($parent->balance ?? 0) - $amount;
                $parent->save();

                $meta = array_merge($metadata, ['balance_type'=>'active','routed_by'=>'sub_account_system_transfer_service','from_sub_account_id'=>(int)$lockedSource->id]);
                if ($transactionType) $meta['transaction_type'] = $transactionType;
                if ($idempotencyKey) $meta['idempotency_key'] = $idempotencyKey;

                $transaction = NajmTransaction::create(['from_account_id'=>(int)$sourceMirror->id,'to_account_id'=>(int)$lockedSystem->id,'amount'=>$amount,'type'=>'immediate','status'=>'completed','metadata'=>$meta,'description'=>$description]);
                LedgerEntry::create(['transaction_id'=>$transaction->id,'account_id'=>(int)$sourceMirror->id,'amount'=>-$amount,'entry_type'=>'debit','meta'=>$meta]);
                LedgerEntry::create(['transaction_id'=>$transaction->id,'account_id'=>(int)$lockedSystem->id,'amount'=>$amount,'entry_type'=>'credit','meta'=>$meta]);
                return $transaction;
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey) return app(FinancialIdempotencyReplayService::class)->replayOrThrow($exception, $idempotencyKey);
            throw $exception;
        }
    }
}
