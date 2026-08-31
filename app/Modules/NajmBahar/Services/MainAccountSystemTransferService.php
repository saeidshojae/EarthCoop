<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Canonical boundary for authorized Active movement from a main account to system. */
class MainAccountSystemTransferService
{
    public function transfer(Account $source, Account $systemAccount, int $amount, ?string $description = null, array $metadata = [], ?string $idempotencyKey = null, ?string $transactionType = null): NajmTransaction
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');
        if (! in_array($source->type, ['user', 'legal_entity'], true)) throw new \RuntimeException('Source must be a main user or legal-entity account.');
        if ($systemAccount->type !== 'system') throw new \RuntimeException('Destination must be a Najm Bahar system account.');

        try {
            return DB::transaction(function () use ($source, $systemAccount, $amount, $description, $metadata, $idempotencyKey, $transactionType) {
                if ($idempotencyKey && ($existing = app(FinancialIdempotencyReplayService::class)->find($idempotencyKey))) return $existing;

                $ids = [(int) $source->id, (int) $systemAccount->id]; sort($ids, SORT_NUMERIC); $locked = [];
                foreach ($ids as $id) $locked[$id] = Account::query()->lockForUpdate()->findOrFail($id);
                $lockedSource = $locked[(int) $source->id]; $lockedSystem = $locked[(int) $systemAccount->id];

                $active = (int) ($lockedSource->balance_active ?? 0);
                $availableActive = app(ActiveBaharReservationService::class)->availableActive($lockedSource);
                if ($active < $amount || $availableActive < $amount) throw new \RuntimeException('Insufficient active funds');

                $lockedSource->balance_active = $active - $amount;
                $lockedSource->balance = (int) $lockedSource->balance_active + (int) ($lockedSource->balance_faded ?? 0) + (int) ($lockedSource->committed_dim ?? 0);
                $lockedSource->save();
                $lockedSystem->balance_active = (int) ($lockedSystem->balance_active ?? 0) + $amount;
                $lockedSystem->balance = (int) ($lockedSystem->balance_active ?? 0) + (int) ($lockedSystem->balance_faded ?? 0) + (int) ($lockedSystem->committed_dim ?? 0);
                $lockedSystem->save();

                $meta = array_merge($metadata, ['balance_type'=>'active','routed_by'=>'main_account_system_transfer_service','from_main_account_id'=>(int)$lockedSource->id]);
                if ($transactionType) $meta['transaction_type'] = $transactionType;
                if ($idempotencyKey) $meta['idempotency_key'] = $idempotencyKey;

                $transaction = NajmTransaction::create(['from_account_id'=>(int)$lockedSource->id,'to_account_id'=>(int)$lockedSystem->id,'amount'=>$amount,'type'=>'immediate','status'=>'completed','metadata'=>$meta,'description'=>$description]);
                LedgerEntry::create(['transaction_id'=>$transaction->id,'account_id'=>(int)$lockedSource->id,'amount'=>-$amount,'entry_type'=>'debit','meta'=>$meta]);
                LedgerEntry::create(['transaction_id'=>$transaction->id,'account_id'=>(int)$lockedSystem->id,'amount'=>$amount,'entry_type'=>'credit','meta'=>$meta]);
                return $transaction;
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey) return app(FinancialIdempotencyReplayService::class)->replayOrThrow($exception, $idempotencyKey);
            throw $exception;
        }
    }
}
