<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Support\Str;

/**
 * Transitional adapter around the legacy SubAccountService.
 *
 * Only the unsafe Main ↔ Sub mutations are overridden here. Other behavior
 * remains inherited until it is migrated deliberately. This lets Release A
 * correct monetary-state preservation without rewriting the entire service in
 * one high-risk change.
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
