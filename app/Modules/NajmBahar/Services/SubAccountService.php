<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Throwable;

/**
 * Non-monetary compatibility surface for sub-account CRUD.
 *
 * Release C deliberately retires all legacy balance mutation implementations
 * from this base service. The container binds this contract to
 * SafeSubAccountService, whose overrides route money movement through the
 * canonical ledger-backed executors. Direct use of this base class therefore
 * fails closed for monetary operations instead of reviving legacy mutations.
 */
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
            $account = \App\Modules\NajmBahar\Models\Account::findOrFail($accountId);
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

            app(AccountService::class)->ensureSubAccountAccount($subAccount);

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
        $this->legacyMutationRemoved();
    }

    public function transferFromSubAccount(
        int $subAccountId,
        int $accountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded'
    ): void {
        $this->legacyMutationRemoved();
    }

    public function transferBetweenSubAccounts(
        int $fromSubAccountId,
        int $toSubAccountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded',
        ?int $transactionId = null
    ): ?NajmTransaction {
        $this->legacyMutationRemoved();
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

    private function legacyMutationRemoved(): never
    {
        throw new \RuntimeException(
            'Legacy SubAccountService monetary mutations were removed in Release C; resolve SubAccountService through the container to use SafeSubAccountService.'
        );
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
