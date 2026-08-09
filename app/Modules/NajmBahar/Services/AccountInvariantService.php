<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Support\Collection;

class AccountInvariantService
{
    public function audit(Account $account): array
    {
        $ownActive = (int) ($account->balance_active ?? 0);
        $ownAvailableDim = (int) ($account->balance_faded ?? 0);
        $ownCommittedDim = (int) ($account->committed_dim ?? 0);
        $ownDim = $ownAvailableDim + $ownCommittedDim;
        $ownTotal = $ownActive + $ownDim;

        $children = SubAccount::where('account_id', $account->id)->get();
        $childActive = (int) $children->sum(fn ($sub) => (int) ($sub->balance_active ?? 0));
        $childDim = (int) $children->sum(fn ($sub) => (int) ($sub->balance_faded ?? 0));
        $childTotal = $childActive + $childDim;
        $aggregate = $ownTotal + $childTotal;
        $stored = (int) ($account->balance ?? 0);

        $balanceSemantics = match (true) {
            $stored === $ownTotal && $stored === $aggregate => 'local_and_aggregate_equal',
            $stored === $ownTotal => 'local_total',
            $stored === $aggregate => 'legacy_aggregate_total',
            default => 'inconsistent',
        };

        $mirrorDrift = [];
        foreach ($children as $sub) {
            $mirror = Account::where('account_number', $sub->sub_account_code)->first();
            if (! $mirror) {
                $mirrorDrift[] = [
                    'sub_account_id' => $sub->id,
                    'code' => $sub->sub_account_code,
                    'issue' => 'missing_account_mirror',
                ];
                continue;
            }

            $subTotal = (int) ($sub->balance_active ?? 0) + (int) ($sub->balance_faded ?? 0);
            if ((int) $sub->balance !== $subTotal
                || (int) $mirror->balance !== $subTotal
                || (int) ($mirror->balance_active ?? 0) !== (int) ($sub->balance_active ?? 0)
                || (int) ($mirror->balance_faded ?? 0) !== (int) ($sub->balance_faded ?? 0)
                || (int) ($mirror->committed_dim ?? 0) !== 0) {
                $mirrorDrift[] = [
                    'sub_account_id' => $sub->id,
                    'code' => $sub->sub_account_code,
                    'issue' => 'subaccount_mirror_drift',
                    'sub_total' => $subTotal,
                    'sub_stored_total' => (int) $sub->balance,
                    'mirror_total' => (int) $mirror->balance,
                    'mirror_active' => (int) ($mirror->balance_active ?? 0),
                    'mirror_dim' => (int) ($mirror->balance_faded ?? 0),
                    'mirror_committed_dim' => (int) ($mirror->committed_dim ?? 0),
                ];
            }
        }

        return [
            'account_id' => $account->id,
            'account_number' => $account->account_number,
            'type' => $account->type,
            'stored_balance' => $stored,
            'own_active' => $ownActive,
            'own_dim_available' => $ownAvailableDim,
            'own_dim_committed' => $ownCommittedDim,
            'own_dim' => $ownDim,
            'own_total' => $ownTotal,
            'child_active' => $childActive,
            'child_dim' => $childDim,
            'child_total' => $childTotal,
            'aggregate_total' => $aggregate,
            'balance_semantics' => $balanceSemantics,
            'mirror_drift' => $mirrorDrift,
            'is_clean' => $balanceSemantics !== 'inconsistent' && $mirrorDrift === [],
        ];
    }

    public function auditAllMainAccounts(): Collection
    {
        return Account::whereIn('type', ['user', 'legal_entity', 'system'])
            ->orderBy('id')
            ->get()
            ->map(fn (Account $account) => $this->audit($account));
    }
}
