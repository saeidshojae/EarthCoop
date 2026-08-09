<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Str;

/**
 * Transitional adapter around the legacy SubAccountService.
 *
 * All active money-moving paths are routed through canonical services. The
 * inherited service remains only for non-monetary CRUD compatibility while
 * Release C removes the remaining dead legacy mutation helpers.
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

            if ($transactionId !== null) {
                throw new \RuntimeException('Existing transaction IDs may only be completed by ScheduledSubAccountTransferExecutor.');
            }

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

        if ($transactionId !== null) {
            throw new \RuntimeException('Existing transaction IDs may only be completed by ScheduledSubAccountTransferExecutor.');
        }

        return app(InternalSubAccountTransferService::class)->transfer(
            $from,
            $to,
            $amount,
            $moneyState,
            $description ?? 'انتقال داخلی بین حساب‌های فرعی',
            $this->sameOwnerIdempotencyKey($from, $to, $amount, $moneyState)
        );
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

    private function sameOwnerIdempotencyKey(SubAccount $from, SubAccount $to, int $amount, string $moneyState): string
    {
        $requestKey = request()?->header('Idempotency-Key');
        if (is_string($requestKey) && trim($requestKey) !== '') {
            return implode('-', [
                'same-owner-subaccount',
                $from->id,
                $to->id,
                $moneyState,
                $amount,
                hash('sha256', trim($requestKey)),
            ]);
        }

        return implode('-', [
            'same-owner-subaccount',
            $from->id,
            $to->id,
            $moneyState,
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
