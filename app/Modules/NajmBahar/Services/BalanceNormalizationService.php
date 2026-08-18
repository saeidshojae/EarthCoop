<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Support\Facades\DB;

class BalanceNormalizationService
{
    public function __construct(private readonly AccountBalanceService $balances)
    {
    }

    /**
     * Build a deterministic, read-only normalization plan.
     * No monetary bucket is changed; only legacy cached `balance` totals are candidates.
     */
    public function plan(): array
    {
        $accountChanges = [];
        $subAccountChanges = [];

        Account::query()->orderBy('id')->chunkById(500, function ($accounts) use (&$accountChanges) {
            foreach ($accounts as $account) {
                $expected = $this->balances->expectedStoredLocalTotal($account);
                $stored = (int) $account->balance;
                if ($stored !== $expected) {
                    $accountChanges[] = [
                        'id' => (int) $account->id,
                        'account_number' => (string) $account->account_number,
                        'stored' => $stored,
                        'expected' => $expected,
                    ];
                }
            }
        });

        SubAccount::query()->orderBy('id')->chunkById(500, function ($subAccounts) use (&$subAccountChanges) {
            foreach ($subAccounts as $sub) {
                $expected = (int) ($sub->balance_active ?? 0) + (int) ($sub->balance_faded ?? 0);
                $stored = (int) $sub->balance;
                if ($stored !== $expected) {
                    $subAccountChanges[] = [
                        'id' => (int) $sub->id,
                        'sub_account_code' => (string) $sub->sub_account_code,
                        'stored' => $stored,
                        'expected' => $expected,
                    ];
                }
            }
        });

        return [
            'accounts' => $accountChanges,
            'subaccounts' => $subAccountChanges,
            'account_change_count' => count($accountChanges),
            'subaccount_change_count' => count($subAccountChanges),
        ];
    }

    /**
     * Apply only cached-total normalization. Active/dim buckets are never altered.
     */
    public function apply(): array
    {
        return DB::transaction(function () {
            $plan = $this->plan();

            foreach ($plan['accounts'] as $change) {
                Account::whereKey($change['id'])->update(['balance' => $change['expected']]);
            }

            foreach ($plan['subaccounts'] as $change) {
                SubAccount::whereKey($change['id'])->update(['balance' => $change['expected']]);
            }

            return $plan;
        }, 3);
    }
}
