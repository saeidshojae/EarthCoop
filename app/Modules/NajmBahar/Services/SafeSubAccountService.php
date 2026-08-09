<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Str;

/**
 * Transitional adapter around the legacy SubAccountService.
 *
 * Unsafe Main ↔ Sub mutations are routed through the canonical internal
 * transfer service. Release C additionally reconciles the Account mirror of
 * every touched SubAccount through AccountInvariantService so all active code
 * paths share one mirror invariant while the rest of the legacy service is
 * migrated incrementally.
 *
 * Dim is constitutional non-transferable money between independent actors.
 * Therefore a faded transfer between sub-accounts is only valid while both
 * sub-accounts belong to the same parent account.
 *
 * Immediate Active transfers between different owners are routed through the
 * canonical TransactionService policy/locking/idempotency path. Scheduled
 * placeholder completion is intentionally not exposed here anymore; only
 * ScheduledSubAccountTransferExecutor may complete an existing transaction ID.
 */
class SafeSubAccountService extends SubAccountService
{
    public function transferToSubAccount(
        int $accountId,
        int $subAccountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded'
    ): void {
        $main = Account::findOrFail($accountId);
        $sub = SubAccount::findOrFail($subAccountId);

        app(InternalAccountTransferService::class)->mainToSub(
            $main,
            $sub,
            $amount,
            $moneyState,
            $description ?? 'انتقال از حساب اصلی به حساب فرعی',
            $this->idempotencyKey('main-to-sub', $main, $sub, $amount, $moneyState)
        );

        app(AccountInvariantService::class)->reconcileSubAccountMirror($sub->fresh());
    }

    public function transferFromSubAccount(
        int $subAccountId,
        int $accountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded'
    ): void {
        $main = Account::findOrFail($accountId);
        $sub = SubAccount::findOrFail($subAccountId);

        app(InternalAccountTransferService::class)->subToMain(
            $sub,
            $main,
            $amount,
            $moneyState,
            $description ?? 'انتقال از حساب فرعی به حساب اصلی',
            $this->idempotencyKey('sub-to-main', $main, $sub, $amount, $moneyState)
        );

        app(AccountInvariantService::class)->reconcileSubAccountMirror($sub->fresh());
    }

    public function transferBetweenSubAccounts(
        int $fromSubAccountId,
        int $toSubAccountId,
        int $amount,
        string $description = null,
        string $moneyState = 'faded',
        ?int $transactionId = null
    ): ?NajmTransaction {
        if ($transactionId !== null) {
            throw new \RuntimeException('Existing transaction IDs may only be completed by ScheduledSubAccountTransferExecutor.');
        }

        $from = SubAccount::findOrFail($fromSubAccountId);
        $to = SubAccount::findOrFail($toSubAccountId);
        $sameOwner = (int) $from->account_id === (int) $to->account_id;

        if ($moneyState === 'faded' && ! $sameOwner) {
            throw new \RuntimeException('پول کمرنگ قابل انتقال بین اشخاص یا نهادهای مستقل نیست.');
        }

        $accountService = app(AccountService::class);
        $fromMirror = $accountService->ensureSubAccountAccount($from);
        $toMirror = $accountService->ensureSubAccountAccount($to);

        if (! $sameOwner && $moneyState === 'active') {
            /** @var SafeTransactionService $transactions */
            $transactions = app(TransactionService::class);
            $transactions->assertEffectiveOwnerTransferAllowed($fromMirror, $toMirror);

            return $transactions->transfer(
                $fromMirror->account_number,
                $toMirror->account_number,
                $amount,
                $description ?? 'انتقال فعال بین حساب‌های فرعی مستقل',
                [
                    'transfer_type' => 'subaccount',
                    'from_sub_account_id' => $from->id,
                    'to_sub_account_id' => $to->id,
                    'from_sub_account_code' => $from->sub_account_code,
                    'to_sub_account_code' => $to->sub_account_code,
                    'money_state' => 'active',
                    'routed_by' => 'safe_sub_account_service',
                ],
                $this->crossOwnerIdempotencyKey($from, $to, $amount),
                'active',
                'subaccount_transfer'
            );
        }

        $transaction = parent::transferBetweenSubAccounts(
            $fromSubAccountId,
            $toSubAccountId,
            $amount,
            $description,
            $moneyState,
            null
        );

        $invariants = app(AccountInvariantService::class);
        $invariants->reconcileSubAccountMirror($from->fresh());
        $invariants->reconcileSubAccountMirror($to->fresh());

        return $transaction;
    }

    private function crossOwnerIdempotencyKey(SubAccount $from, SubAccount $to, int $amount): string
    {
        $requestKey = request()?->header('Idempotency-Key');
        if (is_string($requestKey) && trim($requestKey) !== '') {
            return implode('-', [
                'cross-owner-subaccount-active',
                $from->id,
                $to->id,
                $amount,
                hash('sha256', trim($requestKey)),
            ]);
        }

        return implode('-', [
            'cross-owner-subaccount-active',
            $from->id,
            $to->id,
            $amount,
            (string) Str::uuid(),
        ]);
    }

    private function idempotencyKey(
        string $direction,
        Account $main,
        SubAccount $sub,
        int $amount,
        string $moneyState
    ): string {
        $requestKey = request()?->header('Idempotency-Key');
        if (is_string($requestKey) && trim($requestKey) !== '') {
            return implode('-', [
                'internal',
                $direction,
                $main->id,
                $sub->id,
                $moneyState,
                $amount,
                hash('sha256', trim($requestKey)),
            ]);
        }

        return implode('-', [
            'internal',
            $direction,
            $main->id,
            $sub->id,
            $moneyState,
            $amount,
            (string) Str::uuid(),
        ]);
    }
}
