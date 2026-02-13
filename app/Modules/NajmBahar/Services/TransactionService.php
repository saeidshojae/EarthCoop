<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
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
    public function transfer(
        string|null $fromAccountNumber,
        string $toAccountNumber,
        int $amount,
        string $description = null,
        array $meta = [],
        string|null $idempotencyKey = null,
        string $balanceType = 'balance',
        ?string $transactionType = null
    ): NajmTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return DB::transaction(function () use (
            $fromAccountNumber,
            $toAccountNumber,
            $amount,
            $description,
            $meta,
            $idempotencyKey,
            $balanceType,
            $transactionType
        ) {
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

            $fromSubAccount = null;
            $toSubAccount = null;
            if ($fromAccountNumber) {
                $fromAcc = $accounts[$fromAccountNumber];
                if ($fromAcc->type === 'subaccount') {
                    $fromSubAccount = SubAccount::where('sub_account_code', $fromAcc->account_number)
                        ->lockForUpdate()
                        ->first();
                    if (! $fromSubAccount) {
                        throw new \RuntimeException('Sub-account not found for source account');
                    }
                }
            }

            if ($toAccountNumber) {
                $toAcc = $accounts[$toAccountNumber];
                if ($toAcc->type === 'subaccount') {
                    $toSubAccount = SubAccount::where('sub_account_code', $toAcc->account_number)
                        ->lockForUpdate()
                        ->first();
                    if (! $toSubAccount) {
                        throw new \RuntimeException('Sub-account not found for destination account');
                    }
                }
            }

            $isInternalOwnTransfer = false;
            if ($fromAccountNumber && $toAccountNumber) {
                $fromAcc = $accounts[$fromAccountNumber];
                $toAcc = $accounts[$toAccountNumber];

                if ($fromAcc->type === 'user' && $toAcc->type === 'subaccount' && $toSubAccount) {
                    $isInternalOwnTransfer = intval($toSubAccount->account_id) === intval($fromAcc->id);
                }

                if (! $isInternalOwnTransfer && $fromAcc->type === 'subaccount' && $toAcc->type === 'user' && $fromSubAccount) {
                    $isInternalOwnTransfer = intval($fromSubAccount->account_id) === intval($toAcc->id);
                }
            }

            $systemOperation = (bool) ($meta['system_operation'] ?? false);
            if (! $systemOperation && $fromAccountNumber && $toAccountNumber) {
                $fromAcc = $accounts[$fromAccountNumber];
                $toAcc = $accounts[$toAccountNumber];

                $isSystemTransfer = $fromAcc->type === 'system' || $toAcc->type === 'system';
                if (! $isSystemTransfer) {
                    if ($fromAcc->type === 'user' && $toAcc->type === 'user') {
                        throw new \RuntimeException('انتقال فقط از طریق حساب‌های فرعی ممکن است.');
                    }

                    if ($fromAcc->type === 'user' && $toAcc->type === 'subaccount') {
                        if (! $toSubAccount || intval($toSubAccount->account_id) !== intval($fromAcc->id)) {
                            throw new \RuntimeException('انتقال از حساب اصلی فقط به حساب‌های فرعی خودتان مجاز است.');
                        }
                    }

                    if ($fromAcc->type === 'subaccount' && $toAcc->type === 'user') {
                        if (! $fromSubAccount || intval($fromSubAccount->account_id) !== intval($toAcc->id)) {
                            throw new \RuntimeException('انتقال از حساب فرعی فقط به حساب اصلی خودتان مجاز است.');
                        }
                    }
                } else {
                    if ($fromAcc->type === 'user' && $toAcc->type === 'system') {
                        throw new \RuntimeException('انتقال از حساب اصلی به حساب سیستمی فقط از طریق حساب فرعی مجاز است.');
                    }
                }
            }

            // if from account is specified, check funds
            if ($fromAccountNumber) {
                $fromAcc = $accounts[$fromAccountNumber];
                if ($balanceType === 'active') {
                    $activeBalance = $fromSubAccount ? intval($fromSubAccount->balance_active ?? 0) : intval($fromAcc->balance_active ?? 0);
                    if ($activeBalance < $amount) {
                        throw new \RuntimeException('Insufficient active funds');
                    }
                    if ($fromSubAccount) {
                        $fromSubAccount->balance_active = $activeBalance - $amount;
                    }
                    $fromAcc->balance_active = intval($fromAcc->balance_active ?? 0) - $amount;
                } elseif ($balanceType === 'faded') {
                    $fadedBalance = $fromSubAccount ? intval($fromSubAccount->balance_faded ?? 0) : intval($fromAcc->balance_faded ?? 0);
                    if ($fadedBalance < $amount) {
                        throw new \RuntimeException('Insufficient faded funds');
                    }
                    if ($fromSubAccount) {
                        $fromSubAccount->balance_faded = $fadedBalance - $amount;
                    }
                    $fromAcc->balance_faded = intval($fromAcc->balance_faded ?? 0) - $amount;
                } else {
                    if (intval($fromAcc->balance) < $amount) {
                        throw new \RuntimeException('Insufficient funds');
                    }
                    $fromAcc->balance = intval($fromAcc->balance) - $amount;
                }

                if ($balanceType !== 'balance') {
                    // For internal transfers between user's own accounts (Main ↔ Sub),
                    // only update active/faded, NOT the total balance field
                    // This keeps the parent's total balance constant during internal redistribution
                    if (! ($isInternalOwnTransfer && $fromAcc->type === 'user')) {
                        $fromAcc->balance = intval($fromAcc->balance_active ?? 0) + intval($fromAcc->balance_faded ?? 0);
                    }
                    if ($fromSubAccount) {
                        // ALWAYS ensure subaccount balance = active + faded (never leave it corrupted)
                        $fromSubAccount->balance = intval($fromSubAccount->balance_active ?? 0) + intval($fromSubAccount->balance_faded ?? 0);
                    }
                } else {
                    // balanceType === 'balance', so recalculate total from active+faded  
                    if (! ($isInternalOwnTransfer && $fromAcc->type === 'user')) {
                        $fromAcc->balance = intval($fromAcc->balance_active ?? 0) + intval($fromAcc->balance_faded ?? 0);
                    }
                    if ($fromSubAccount) {
                        // ALWAYS ensure subaccount balance = active + faded (never leave it corrupted)
                        $fromSubAccount->balance = intval($fromSubAccount->balance_active ?? 0) + intval($fromSubAccount->balance_faded ?? 0);
                    }
                }
                $fromAcc->save();
                if ($fromSubAccount) {
                    $fromSubAccount->save();
                }
            }

            // credit
            $toAcc = $accounts[$toAccountNumber];
            if ($balanceType === 'active') {
                $toAcc->balance_active = intval($toAcc->balance_active ?? 0) + $amount;
                if ($toSubAccount) {
                    $toSubAccount->balance_active = intval($toSubAccount->balance_active ?? 0) + $amount;
                }
            } elseif ($balanceType === 'faded') {
                $toAcc->balance_faded = intval($toAcc->balance_faded ?? 0) + $amount;
                if ($toSubAccount) {
                    $toSubAccount->balance_faded = intval($toSubAccount->balance_faded ?? 0) + $amount;
                }
            } else {
                $toAcc->balance = intval($toAcc->balance) + $amount;
            }

            if ($balanceType !== 'balance') {
                if (! ($isInternalOwnTransfer && $toAcc->type === 'user')) {
                    $toAcc->balance = intval($toAcc->balance_active ?? 0) + intval($toAcc->balance_faded ?? 0);
                }
                if ($toSubAccount) {
                    // ALWAYS ensure subaccount balance = active + faded (never leave it corrupted)
                    $toSubAccount->balance = intval($toSubAccount->balance_active ?? 0) + intval($toSubAccount->balance_faded ?? 0);
                }
            } else {
                // balanceType === 'balance', so recalculate total from active+faded
                if (! ($isInternalOwnTransfer && $toAcc->type === 'user')) {
                    $toAcc->balance = intval($toAcc->balance_active ?? 0) + intval($toAcc->balance_faded ?? 0);
                }
                if ($toSubAccount) {
                    // ALWAYS ensure subaccount balance = active + faded (never leave it corrupted)
                    $toSubAccount->balance = intval($toSubAccount->balance_active ?? 0) + intval($toSubAccount->balance_faded ?? 0);
                }
            }

            $toAcc->save();
            if ($toSubAccount) {
                $toSubAccount->save();
            }
            
            // If funds are transferred FROM a subaccount to ANY system account (0000000000-*), 
            // also deduct from the main user account to keep totals consistent
            // System accounts have account codes starting with '0000000000-'
            if ($fromSubAccount && substr($toAccountNumber, 0, 10) === '0000000000') {
                $mainAccountNumber = substr($fromAccountNumber, 0, 10);
                if ($mainAccountNumber) {
                    $mainAccount = Account::where('account_number', $mainAccountNumber)
                        ->where('type', 'user')
                        ->lockForUpdate()
                        ->first();
                    if ($mainAccount) {
                        // For external transfers from subaccount, deduct directly from main balance
                        // DO NOT deduct from balance_active/faded as those already reflect internal allocations
                        // Just reduce the total balance field to represent funds leaving the system
                        $mainAccount->balance = intval($mainAccount->balance ?? 0) - $amount;
                        $mainAccount->save();
                    }
                }
                
                // Also ensure subaccount's balance field is recalculated from active+faded
                // because we deducted from balance_active in the debit section
                $fromSubAccount->balance = intval($fromSubAccount->balance_active ?? 0) + intval($fromSubAccount->balance_faded ?? 0);
                $fromSubAccount->save();
            }

            // create transaction record
            // merge idempotency key into metadata
            if ($idempotencyKey) {
                $meta['idempotency_key'] = $idempotencyKey;
            }
            $meta['balance_type'] = $balanceType;
            if ($transactionType) {
                $meta['transaction_type'] = $transactionType;
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
        $accountIds = $this->getUserAccountIds($userId);
        if (empty($accountIds)) {
            return collect();
        }

        return NajmTransaction::where(function($query) use ($accountIds) {
            $query->whereIn('from_account_id', $accountIds)
                  ->orWhereIn('to_account_id', $accountIds);
        })
        ->with(['fromAccount', 'toAccount'])
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get();
    }

    public function getUserAccountIds(int $userId): array
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
        $account = Account::where('account_number', $accountNumber)->first();
        if (! $account) {
            return [];
        }

        $accountIds = [$account->id];
        $subAccounts = SubAccount::where('account_id', $account->id)->get();
        $accountService = app(AccountService::class);

        foreach ($subAccounts as $subAccount) {
            $subAccountAccount = $accountService->ensureSubAccountAccount($subAccount);
            $accountIds[] = $subAccountAccount->id;
        }

        return array_values(array_unique($accountIds));
    }

    /**
     * واریز اولیه با تقسیم موجودی بر اساس درصد اکتیو/فیدد
     * بدون نیاز به Transaction record
     */
    /**
     * واریز اولیه با تقسیم به موجودی فعال و کمرنگ
     * 
     * @param string $toAccountNumber شماره حساب مقصد
     * @param int $amount مبلغ کل (به واحد گل)
     * @param int $activePercentage درصد موجودی فعال (0-100) - فقط در حالت percentage
     * @param int|null $activeFixedAmount مبلغ ثابت موجودی فعال - فقط در حالت fixed_amount
     * @param string $type نوع تخصیص: 'percentage' یا 'fixed_amount'
     * @return Account
     */
    public function depositInitialFunding(
        string $toAccountNumber, 
        int $amount, 
        int $activePercentage = 30,
        ?int $activeFixedAmount = null,
        string $type = 'percentage'
    ): Account {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return DB::transaction(function () use ($toAccountNumber, $amount, $activePercentage, $activeFixedAmount, $type) {
            $account = Account::where('account_number', $toAccountNumber)->lockForUpdate()->first();
            if (!$account) {
                throw new \RuntimeException("Account not found: {$toAccountNumber}");
            }

            // محاسبه موجودی فعال و کمرنگ
            if ($type === 'fixed_amount' && $activeFixedAmount !== null) {
                // حالت مبلغ ثابت
                $activeAmount = min($activeFixedAmount, $amount); // حداکثر به اندازه کل مبلغ
            } else {
                // حالت درصدی
                if ($activePercentage < 0 || $activePercentage > 100) {
                    throw new \InvalidArgumentException('Active percentage must be between 0 and 100');
                }
                $activeAmount = intval(($amount * $activePercentage) / 100);
            }
            
            $fadedAmount = $amount - $activeAmount;

            // بروزرسانی موجودی
            if (!isset($account->balance_active)) {
                $account->balance_active = 0;
            }
            if (!isset($account->balance_faded)) {
                $account->balance_faded = 0;
            }

            $account->balance_active = intval($account->balance_active) + $activeAmount;
            $account->balance_faded = intval($account->balance_faded) + $fadedAmount;
            $account->balance = intval($account->balance_active) + intval($account->balance_faded);
            $account->save();

            return $account;
        });
    }
}
