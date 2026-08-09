<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;

class AccountBalanceService
{
    /**
     * Canonical rule for Release A:
     * - an Account row represents only its own local active/dim buckets;
     * - wallet/owner totals are derived by aggregating the main account and its
     *   child sub-accounts;
     * - `Account.balance` is transitional legacy data and MUST NOT be used to
     *   calculate economic ownership while normalization is in progress.
     */
    public function local(Account $account): array
    {
        $active = (int) ($account->balance_active ?? 0);
        $dim = (int) ($account->balance_faded ?? 0);

        return [
            'active' => $active,
            'dim' => $dim,
            'total' => $active + $dim,
        ];
    }

    public function aggregate(Account $mainAccount): array
    {
        $main = $this->local($mainAccount);

        $subAccounts = SubAccount::query()
            ->where('account_id', $mainAccount->id)
            ->get();

        $subActive = (int) $subAccounts->sum(fn (SubAccount $sub) => (int) ($sub->balance_active ?? 0));
        $subDim = (int) $subAccounts->sum(fn (SubAccount $sub) => (int) ($sub->balance_faded ?? 0));

        return [
            'active' => $main['active'] + $subActive,
            'dim' => $main['dim'] + $subDim,
            'total' => $main['total'] + $subActive + $subDim,
            'main' => $main,
            'subaccounts' => [
                'active' => $subActive,
                'dim' => $subDim,
                'total' => $subActive + $subDim,
                'count' => $subAccounts->count(),
            ],
        ];
    }

    public function expectedStoredLocalTotal(Account $account): int
    {
        return $this->local($account)['total'];
    }
}
