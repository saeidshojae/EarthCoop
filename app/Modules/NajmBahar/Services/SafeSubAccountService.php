<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;

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
            $this->idempotencyKey('main-to-sub', $main, $sub, $amount, $moneyState, $description)
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
            $this->idempotencyKey('sub-to-main', $main, $sub, $amount, $moneyState, $description)
        );
    }

    private function idempotencyKey(
        string $direction,
        Account $main,
        SubAccount $sub,
        int $amount,
        string $moneyState,
        ?string $description
    ): string {
        // UI actions do not currently supply a request id. Use a high-entropy
        // operation key so the canonical service has an idempotency boundary;
        // API/controller request-id propagation will replace this during the
        // concurrency hardening pass.
        return implode('-', [
            'internal',
            $direction,
            $main->id,
            $sub->id,
            $moneyState,
            $amount,
            now()->format('YmdHisv'),
            substr(hash('sha256', (string) $description . microtime(true) . random_int(1, PHP_INT_MAX)), 0, 16),
        ]);
    }
}
