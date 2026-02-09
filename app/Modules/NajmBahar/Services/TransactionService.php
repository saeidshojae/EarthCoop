<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Exception as QueryException;

class TransactionService
{
    /**
     * Perform an atomic immediate transfer between two Najm accounts.
     * - $fromAccountNumber or null for system
     * - $toAccountNumber or null for system
     * - $amount in integer (behar smallest unit)
     * Returns NajmTransaction on success.
     * Throws exceptions on validation/insufficient funds.
     */
    public function transfer(string|null $fromAccountNumber, string $toAccountNumber, int $amount, string $description = null, array $meta = [], string|null $idempotencyKey = null): NajmTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return DB::transaction(function () use ($fromAccountNumber, $toAccountNumber, $amount, $description, $meta, $idempotencyKey) {
            // idempotency: if provided, return existing transaction
            if ($idempotencyKey) {
                $existing = NajmTransaction::where('metadata->idempotency_key', $idempotencyKey)->first();
                if ($existing) return $existing;
            }
            // lock both accounts (order by account_number to avoid deadlocks)
            $numbers = array_filter([$fromAccountNumber, $toAccountNumber]);
            sort($numbers, SORT_STRING);

            $accounts = [];
            foreach ($numbers as $num) {
                $accounts[$num] = Account::where('account_number', $num)->lockForUpdate()->first();
                if (!$accounts[$num]) {
                    throw new \RuntimeException("Account not found: {$num}");
                }
            }

            if ($fromAccountNumber && $toAccountNumber) {
                $fromAcc = $accounts[$fromAccountNumber];
                $toAcc = $accounts[$toAccountNumber];

                $isSystemTransfer = $fromAcc->type === 'system' || $toAcc->type === 'system';
                $isSameUserTransfer = $fromAcc->user_id && $toAcc->user_id && intval($fromAcc->user_id) === intval($toAcc->user_id);

                if (! $isSystemTransfer && ! $isSameUserTransfer && $fromAcc->user_id && $toAcc->user_id) {
                    $setting = Setting::first();
                    $threshold = $setting?->najm_bahar_user_threshold ?? 1111111;
                    $userCount = User::count();

                    if ($userCount < $threshold) {
                        throw new \RuntimeException('همه تراکنشهای بین کاربران قفله. تبادل در خود حساب بین اصلی و فرعی و بالعکس و همچنین کلیه تراکنشهای سیستمی مثل واریز و برداشت برای سیستم بازه.');
                    }
                }
            }

            // if from account is specified, check funds
            if ($fromAccountNumber) {
                $fromAcc = $accounts[$fromAccountNumber];
                if (intval($fromAcc->balance) < $amount) {
                    throw new \RuntimeException('Insufficient funds');
                }
                // debit
                $fromAcc->balance = intval($fromAcc->balance) - $amount;
                $fromAcc->save();
            }

            // credit
            $toAcc = $accounts[$toAccountNumber];
            $toAcc->balance = intval($toAcc->balance) + $amount;
            $toAcc->save();

            // create transaction record
            // merge idempotency key into metadata
            if ($idempotencyKey) {
                $meta['idempotency_key'] = $idempotencyKey;
            }

            $tx = NajmTransaction::create([
                'from_account_id' => $fromAccountNumber ? $accounts[$fromAccountNumber]->id : null,
                'to_account_id' => $toAcc->id,
                'amount' => $amount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => $meta,
                'description' => $description,
            ]);

            // ledger entries (double-entry)
            if ($fromAccountNumber) {
                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $accounts[$fromAccountNumber]->id,
                    'amount' => -$amount,
                    'entry_type' => 'debit',
                    'meta' => $meta,
                ]);
            }

            LedgerEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $toAcc->id,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => $meta,
            ]);

            return $tx;
        });
    }

    /**
     * Adjust account balance with a single-sided entry (credit or debit).
     */
    public function adjust(string $accountNumber, int $amount, string $direction, string $description = null, array $meta = [], string|null $idempotencyKey = null): NajmTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (!in_array($direction, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Direction must be credit or debit');
        }

        return DB::transaction(function () use ($accountNumber, $amount, $direction, $description, $meta, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = NajmTransaction::where('metadata->idempotency_key', $idempotencyKey)->first();
                if ($existing) return $existing;
            }

            $account = Account::where('account_number', $accountNumber)->lockForUpdate()->first();
            if (!$account) {
                throw new \RuntimeException("Account not found: {$accountNumber}");
            }

            if ($direction === 'debit' && intval($account->balance) < $amount) {
                throw new \RuntimeException('Insufficient funds');
            }

            $account->balance = $direction === 'credit'
                ? intval($account->balance) + $amount
                : intval($account->balance) - $amount;
            $account->save();

            if ($idempotencyKey) {
                $meta['idempotency_key'] = $idempotencyKey;
            }
            $meta['adjustment_direction'] = $direction;

            $tx = NajmTransaction::create([
                'from_account_id' => $direction === 'debit' ? $account->id : null,
                'to_account_id' => $direction === 'credit' ? $account->id : null,
                'amount' => $amount,
                'type' => 'adjustment',
                'status' => 'completed',
                'metadata' => $meta,
                'description' => $description,
            ]);

            LedgerEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $account->id,
                'amount' => $direction === 'credit' ? $amount : -$amount,
                'entry_type' => $direction,
                'meta' => $meta,
            ]);

            return $tx;
        });
    }

    /**
     * Get user transactions
     */
    public function getUserTransactions(int $userId, int $limit = 20)
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
        $account = Account::where('account_number', $accountNumber)->first();
        
        if (!$account) {
            return collect();
        }

        return NajmTransaction::where(function($query) use ($account) {
            $query->where('from_account_id', $account->id)
                  ->orWhere('to_account_id', $account->id);
        })
        ->with(['fromAccount', 'toAccount'])
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get();
    }
}
