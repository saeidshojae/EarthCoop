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

        if ($moneyState === 'faded' && (int) $from->account_id !== (int) $to->account_id) {
            throw new \RuntimeException('پول کمرنگ قابل انتقال بین اشخاص یا نهادهای مستقل نیست.');
        }

        $transaction = parent::transferBetweenSubAccounts(
            $fromSubAccountId,
            $toSubAccountId,
            $amount,
            $description,
            $moneyState,
            $transactionId
        );

        $invariants = app(AccountInvariantService::class);
        $invariants->reconcileSubAccountMirror($from->fresh());
        $invariants->reconcileSubAccountMirror($to->fresh());

        return $transaction;
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

        // A fresh operation without a caller-provided retry key is intentionally
        // unique. HTTP/API clients should send Idempotency-Key for retry safety.
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
