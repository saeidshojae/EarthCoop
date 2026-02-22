<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubAccountService
{
    public function createSubAccount(int $accountId, string $name = null): SubAccount
    {
        $context = [
            'account_id' => $accountId,
            'name' => $name,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.create.requested', $context);

        try {
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

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.create.succeeded', array_merge($context, [
                'sub_account_id' => (int) $subAccount->id,
                'sub_account_code' => (string) $subAccount->sub_account_code,
            ]));
            return $subAccount;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.create.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
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
        $context = [
            'account_id' => $accountId,
            'sub_account_id' => $subAccountId,
            'amount' => $amount,
            'money_state' => $moneyState,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_to.requested', $context);

        try {
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
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_to.succeeded', $context);
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_to.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function transferFromSubAccount(
        int $subAccountId,
        int $accountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded'
    ): void {
        $context = [
            'sub_account_id' => $subAccountId,
            'account_id' => $accountId,
            'amount' => $amount,
            'money_state' => $moneyState,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_from.requested', $context);

        try {
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
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_from.succeeded', $context);
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_from.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function transferBetweenSubAccounts(
        int $fromSubAccountId,
        int $toSubAccountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded',
        ?int $transactionId = null
    ): ?NajmTransaction {
        $context = [
            'from_sub_account_id' => $fromSubAccountId,
            'to_sub_account_id' => $toSubAccountId,
            'amount' => $amount,
            'money_state' => $moneyState,
            'transaction_id' => $transactionId,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_between.requested', $context);

        try {
            $result = DB::transaction(function () use ($fromSubAccountId, $toSubAccountId, $amount, $moneyState, $description, $transactionId) {
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
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_between.succeeded', array_merge($context, [
                'result_transaction_id' => (int) ($result?->id ?? 0),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.transfer_between.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function deactivateSubAccount(int $subAccountId): void
    {
        $context = [
            'sub_account_id' => $subAccountId,
            'scope' => 'economy:najm-bahar',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.deactivate.requested', $context);

        try {
            $subAccount = SubAccount::findOrFail($subAccountId);
            $subAccount->status = 0;
            $subAccount->save();
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.deactivate.succeeded', $context);
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.deactivate.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function activateSubAccount(int $subAccountId): void
    {
        $context = [
            'sub_account_id' => $subAccountId,
            'scope' => 'economy:najm-bahar',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.activate.requested', $context);

        try {
            $subAccount = SubAccount::findOrFail($subAccountId);
            $subAccount->status = 1;
            $subAccount->save();
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.activate.succeeded', $context);
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.sub_account.activate.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
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

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);
            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // Telemetry must not break sub-account flows.
        }
    }
}

