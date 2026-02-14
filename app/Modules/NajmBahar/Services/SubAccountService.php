<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Support\Facades\DB;

class SubAccountService
{
    public function createSubAccount(int $accountId, string $name = null): SubAccount
    {
        $account = Account::findOrFail($accountId);

        $lastSubAccount = SubAccount::where('account_id', $accountId)
            ->orderBy('sub_account_code', 'desc')
            ->first();

        $subAccountCode = $this->generateSubAccountCode($account->account_number, $lastSubAccount);

        $subAccount = SubAccount::create([
            'account_id' => $accountId,
            'sub_account_code' => $subAccountCode,
            'name' => $name ?? 'Sub Account ' . $subAccountCode,
            'balance' => 0,
            'balance_faded' => 0,
            'balance_active' => 0,
            'status' => 1,
        ]);

        $accountService = app(AccountService::class);
        $accountService->ensureSubAccountAccount($subAccount);

        return $subAccount;
    }

    private function generateSubAccountCode(string $accountNumber, ?SubAccount $lastSubAccount = null): string
    {
        if ($lastSubAccount) {
            $parts = explode('-', $lastSubAccount->sub_account_code);
            $lastNumber = isset($parts[1]) ? intval($parts[1]) : 0;
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return $accountNumber . '-' . $newNumber;
    }

    public function getSubAccountsForAccount(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        return SubAccount::where('account_id', $accountId)
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAllSubAccountsForAccount(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        return SubAccount::where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function transferToSubAccount(
        int $accountId,
        int $subAccountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded'
    ): void {
        DB::transaction(function () use ($accountId, $subAccountId, $amount, $moneyState) {
            $account = Account::lockForUpdate()->findOrFail($accountId);
            $subAccount = SubAccount::lockForUpdate()->findOrFail($subAccountId);

            if ($subAccount->account_id !== $accountId) {
                throw new \RuntimeException('SubAccount does not belong to this account');
            }

            if ($subAccount->status !== 1) {
                throw new \RuntimeException('SubAccount is inactive');
            }

            $this->ensureState($moneyState);
            $this->applyMainToSubTransfer($account, $subAccount, $amount, $moneyState);
        });
    }

    public function transferFromSubAccount(
        int $subAccountId,
        int $accountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded'
    ): void {
        DB::transaction(function () use ($subAccountId, $accountId, $amount, $moneyState) {
            $subAccount = SubAccount::lockForUpdate()->findOrFail($subAccountId);
            $account = Account::lockForUpdate()->findOrFail($accountId);

            if ($subAccount->account_id !== $accountId) {
                throw new \RuntimeException('SubAccount does not belong to this account');
            }

            if ($subAccount->status !== 1) {
                throw new \RuntimeException('SubAccount is inactive');
            }

            $this->ensureState($moneyState);
            $this->applySubToMainTransfer($subAccount, $account, $amount, $moneyState);
        });
    }

    public function transferBetweenSubAccounts(
        int $fromSubAccountId,
        int $toSubAccountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded',
        ?int $transactionId = null
    ): ?NajmTransaction {
        return DB::transaction(function () use ($fromSubAccountId, $toSubAccountId, $amount, $moneyState, $description, $transactionId) {
            $fromSubAccount = SubAccount::lockForUpdate()->findOrFail($fromSubAccountId);
            $toSubAccount = SubAccount::lockForUpdate()->findOrFail($toSubAccountId);

            if ($fromSubAccount->id === $toSubAccount->id) {
                throw new \RuntimeException('Source and destination sub-accounts are the same');
            }

            if ($fromSubAccount->status !== 1 || $toSubAccount->status !== 1) {
                throw new \RuntimeException('Sub-account is inactive');
            }

            $this->ensureState($moneyState);

            $accountService = app(AccountService::class);
            $fromAccount = $accountService->ensureSubAccountAccount($fromSubAccount);
            $toAccount = $accountService->ensureSubAccountAccount($toSubAccount);

            $this->applySubToSubTransfer($fromSubAccount, $toSubAccount, $amount, $moneyState);

            if ((int) $fromAccount->balance !== (int) $fromSubAccount->balance) {
                $fromAccount->balance = (int) $fromSubAccount->balance;
                $fromAccount->save();
            }

            if ((int) $toAccount->balance !== (int) $toSubAccount->balance) {
                $toAccount->balance = (int) $toSubAccount->balance;
                $toAccount->save();
            }

            $meta = [
                'transfer_type' => 'subaccount',
                'from_sub_account_id' => $fromSubAccount->id,
                'to_sub_account_id' => $toSubAccount->id,
                'from_sub_account_code' => $fromSubAccount->sub_account_code,
                'to_sub_account_code' => $toSubAccount->sub_account_code,
                'money_state' => $moneyState,
            ];

            if ($transactionId) {
                $tx = NajmTransaction::find($transactionId);
                if (! $tx) {
                    $tx = NajmTransaction::create([
                        'from_account_id' => $fromAccount->id,
                        'to_account_id' => $toAccount->id,
                        'amount' => $amount,
                        'type' => 'scheduled',
                        'status' => 'completed',
                        'metadata' => $meta,
                        'description' => $description,
                    ]);
                } else {
                    $tx->from_account_id = $fromAccount->id;
                    $tx->to_account_id = $toAccount->id;
                    $tx->amount = $amount;
                    $tx->status = 'completed';
                    $tx->metadata = array_merge((array) ($tx->metadata ?? []), $meta);
                    if ($description) {
                        $tx->description = $description;
                    }
                    $tx->save();
                }
            } else {
                $tx = NajmTransaction::create([
                    'from_account_id' => $fromAccount->id,
                    'to_account_id' => $toAccount->id,
                    'amount' => $amount,
                    'type' => 'immediate',
                    'status' => 'completed',
                    'metadata' => $meta,
                    'description' => $description,
                ]);
            }

            if (! LedgerEntry::where('transaction_id', $tx->id)->exists()) {
                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $fromAccount->id,
                    'amount' => -$amount,
                    'entry_type' => 'debit',
                    'meta' => $meta,
                ]);

                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $toAccount->id,
                    'amount' => $amount,
                    'entry_type' => 'credit',
                    'meta' => $meta,
                ]);
            }

            return $tx;
        });
    }

    public function deactivateSubAccount(int $subAccountId): void
    {
        $subAccount = SubAccount::findOrFail($subAccountId);
        $subAccount->status = 0;
        $subAccount->save();
    }

    public function activateSubAccount(int $subAccountId): void
    {
        $subAccount = SubAccount::findOrFail($subAccountId);
        $subAccount->status = 1;
        $subAccount->save();
    }

    private function ensureState(string $moneyState): void
    {
        if (!in_array($moneyState, ['active', 'faded'], true)) {
            throw new \InvalidArgumentException('Money state must be active or faded');
        }
    }

    private function applyMainToSubTransfer(Account $main, SubAccount $sub, int $amount, string $moneyState): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if ($moneyState === 'active') {
            if (intval($main->balance_active ?? 0) < $amount) {
                throw new \RuntimeException('Insufficient active funds');
            }

            $main->balance_active = intval($main->balance_active ?? 0) - $amount;
            $sub->balance_active = intval($sub->balance_active ?? 0) + $amount;
        } else {
            if (intval($main->balance_faded ?? 0) < $amount) {
                throw new \RuntimeException('Insufficient faded funds');
            }

            $main->balance_faded = intval($main->balance_faded ?? 0) - $amount;
            $sub->balance_faded = intval($sub->balance_faded ?? 0) + $amount;
        }

        $this->syncTotals($main, $sub);
    }

    private function applySubToMainTransfer(SubAccount $sub, Account $main, int $amount, string $moneyState): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if ($moneyState === 'active') {
            if (intval($sub->balance_active ?? 0) < $amount) {
                throw new \RuntimeException('Insufficient active funds in sub-account');
            }

            $sub->balance_active = intval($sub->balance_active ?? 0) - $amount;
            $main->balance_active = intval($main->balance_active ?? 0) + $amount;
        } else {
            if (intval($sub->balance_faded ?? 0) < $amount) {
                throw new \RuntimeException('Insufficient faded funds in sub-account');
            }

            $sub->balance_faded = intval($sub->balance_faded ?? 0) - $amount;
            $main->balance_active = intval($main->balance_active ?? 0) + $amount;
        }

        $this->syncTotals($main, $sub);
    }

    private function applySubToSubTransfer(SubAccount $from, SubAccount $to, int $amount, string $moneyState): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if ($moneyState === 'active') {
            if (intval($from->balance_active ?? 0) < $amount) {
                throw new \RuntimeException('Insufficient active funds in sub-account');
            }

            $from->balance_active = intval($from->balance_active ?? 0) - $amount;
            $to->balance_active = intval($to->balance_active ?? 0) + $amount;
        } else {
            if (intval($from->balance_faded ?? 0) < $amount) {
                throw new \RuntimeException('Insufficient faded funds in sub-account');
            }

            $from->balance_faded = intval($from->balance_faded ?? 0) - $amount;
            $to->balance_faded = intval($to->balance_faded ?? 0) + $amount;
        }

        $from->balance = intval($from->balance_active ?? 0) + intval($from->balance_faded ?? 0);
        $to->balance = intval($to->balance_active ?? 0) + intval($to->balance_faded ?? 0);
        $from->save();
        $to->save();
    }

    private function syncTotals(Account $main, SubAccount $sub): void
    {
        $sub->balance = intval($sub->balance_active ?? 0) + intval($sub->balance_faded ?? 0);

        $main->save();
        $sub->save();

        // Keep the Account mirror for this subaccount aligned.
        $subAccountMirror = Account::where('account_number', $sub->sub_account_code)->first();
        if ($subAccountMirror) {
            $subAccountMirror->balance = (int) $sub->balance;
            $subAccountMirror->balance_active = (int) ($sub->balance_active ?? 0);
            $subAccountMirror->balance_faded = (int) ($sub->balance_faded ?? 0);
            $subAccountMirror->save();
        }
    }
}

