<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;

class AccountBalanceService
{
    /**
     * Canonical Release B rule:
     * - balance_faded is available Dim;
     * - committed_dim is reserved/committed Dim;
     * - neither commitment nor release changes total monetary ownership;
     * - Account.balance remains the local sum of active + available Dim + committed Dim.
     */
    public function local(Account $account): array
    {
        $active = (int) ($account->balance_active ?? 0);
        $availableDim = (int) ($account->balance_faded ?? 0);
        $committedDim = (int) ($account->committed_dim ?? 0);
        $dim = $availableDim + $committedDim;

        return [
            'active' => $active,
            'dim_available' => $availableDim,
            'dim_committed' => $committedDim,
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
            'dim_available' => $main['dim_available'] + $subDim,
            'dim_committed' => $main['dim_committed'],
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
