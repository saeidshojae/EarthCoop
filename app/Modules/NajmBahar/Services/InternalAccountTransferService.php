<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Support\Facades\DB;

class InternalAccountTransferService
{
    public function __construct(private readonly AccountService $accountService)
    {
    }

    public function mainToSub(
        Account $main,
        SubAccount $sub,
        int $amount,
        string $moneyState,
        string $description,
        string $idempotencyKey,
        array $meta = []
    ): Transaction {
        return $this->move($main, $sub, $amount, $moneyState, true, $description, $idempotencyKey, $meta);
    }

    public function subToMain(
        SubAccount $sub,
        Account $main,
        int $amount,
        string $moneyState,
        string $description,
        string $idempotencyKey,
        array $meta = []
    ): Transaction {
        return $this->move($main, $sub, $amount, $moneyState, false, $description, $idempotencyKey, $meta);
    }

    private function move(
        Account $main,
        SubAccount $sub,
        int $amount,
        string $moneyState,
        bool $toSub,
        string $description,
        string $idempotencyKey,
        array $meta
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Internal transfer amount must be positive.');
        }

        if (! in_array($moneyState, ['active', 'faded'], true)) {
            throw new \InvalidArgumentException('Money state must be active or faded.');
        }

        return DB::transaction(function () use (
            $main,
            $sub,
            $amount,
            $moneyState,
            $toSub,
            $description,
            $idempotencyKey,
            $meta
        ) {
            $existing = Transaction::where('metadata->idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $lockedMain = Account::whereKey($main->id)->lockForUpdate()->firstOrFail();
            $lockedSub = SubAccount::whereKey($sub->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedSub->account_id !== (int) $lockedMain->id) {
                throw new \RuntimeException('Sub-account does not belong to the main account.');
            }

            if ((int) $lockedSub->status !== 1) {
                throw new \RuntimeException('Sub-account is inactive.');
            }

            $mirror = $this->accountService->ensureSubAccountAccount($lockedSub);
            $lockedMirror = Account::whereKey($mirror->id)->lockForUpdate()->firstOrFail();

            $column = $moneyState === 'active' ? 'balance_active' : 'balance_faded';
            $source = $toSub ? $lockedMain : $lockedSub;
            $available = (int) ($source->{$column} ?? 0);
            if ($available < $amount) {
                throw new \RuntimeException("Insufficient {$moneyState} funds for internal transfer.");
            }

            if ($toSub) {
                $lockedMain->{$column} = (int) ($lockedMain->{$column} ?? 0) - $amount;
                $lockedSub->{$column} = (int) ($lockedSub->{$column} ?? 0) + $amount;
            } else {
                $lockedSub->{$column} = (int) ($lockedSub->{$column} ?? 0) - $amount;
                $lockedMain->{$column} = (int) ($lockedMain->{$column} ?? 0) + $amount;
            }

            // Canonical semantics: every stored Account.balance is LOCAL only.
            $lockedMain->balance = (int) ($lockedMain->balance_active ?? 0) + (int) ($lockedMain->balance_faded ?? 0);
            $lockedSub->balance = (int) ($lockedSub->balance_active ?? 0) + (int) ($lockedSub->balance_faded ?? 0);
            $lockedMain->save();
            $lockedSub->save();

            $lockedMirror->balance_active = (int) ($lockedSub->balance_active ?? 0);
            $lockedMirror->balance_faded = (int) ($lockedSub->balance_faded ?? 0);
            $lockedMirror->balance = (int) $lockedSub->balance;
            $lockedMirror->save();

            $eventMeta = array_merge($meta, [
                'type' => 'internal_account_transfer',
                'transfer_type' => $toSub ? 'main_to_subaccount' : 'subaccount_to_main',
                'money_state' => $moneyState,
                'idempotency_key' => $idempotencyKey,
                'main_account_id' => $lockedMain->id,
                'sub_account_id' => $lockedSub->id,
                'sub_account_code' => $lockedSub->sub_account_code,
            ]);

            $fromAccountId = $toSub ? $lockedMain->id : $lockedMirror->id;
            $toAccountId = $toSub ? $lockedMirror->id : $lockedMain->id;

            $transaction = Transaction::create([
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => $eventMeta,
                'description' => $description,
            ]);

            foreach ([
                [$fromAccountId, -$amount, 'debit'],
                [$toAccountId, $amount, 'credit'],
            ] as [$accountId, $entryAmount, $entryType]) {
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $accountId,
                    'amount' => $entryAmount,
                    'entry_type' => $entryType,
                    'meta' => array_merge($eventMeta, ['balance_bucket' => $moneyState]),
                ]);
            }

            return $transaction;
        });
    }
}
