<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Repair only the canonical SubAccount <-> Account mirror invariant.
     *
     * This deliberately does not rewrite the parent account balance because
     * legacy parents may still use aggregate-total semantics during Release C.
     */
    public function reconcileSubAccountMirror(SubAccount $subAccount): array
    {
        return DB::transaction(function () use ($subAccount) {
            $lockedSub = SubAccount::query()
                ->whereKey($subAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            $mirror = Account::query()
                ->where('account_number', $lockedSub->sub_account_code)
                ->where('type', 'subaccount')
                ->lockForUpdate()
                ->first();

            if (! $mirror) {
                throw new \RuntimeException('Sub-account mirror account is missing.');
            }

            $active = (int) ($lockedSub->balance_active ?? 0);
            $dim = (int) ($lockedSub->balance_faded ?? 0);
            $total = $active + $dim;

            $lockedSub->balance = $total;
            $lockedSub->save();

            $mirror->balance_active = $active;
            $mirror->balance_faded = $dim;
            $mirror->committed_dim = 0;
            $mirror->balance = $total;
            $mirror->save();

            return [
                'sub_account_id' => (int) $lockedSub->id,
                'mirror_account_id' => (int) $mirror->id,
                'active' => $active,
                'dim' => $dim,
                'total' => $total,
            ];
        });
    }

    /**
     * Transitional reverse reconciliation for monetary operations that still
     * receive the Account mirror as their mutation target.
     *
     * This is intentionally narrow: only an existing `subaccount` Account can
     * drive its matching SubAccount row. It never creates a missing child and
     * never changes the parent account semantics.
     */
    public function reconcileSubAccountFromMirror(Account $account): ?array
    {
        if ($account->type !== 'subaccount') {
            return null;
        }

        return DB::transaction(function () use ($account) {
            $mirror = Account::query()
                ->whereKey($account->id)
                ->where('type', 'subaccount')
                ->lockForUpdate()
                ->firstOrFail();

            $sub = SubAccount::query()
                ->where('sub_account_code', $mirror->account_number)
                ->lockForUpdate()
                ->first();

            if (! $sub) {
                return null;
            }

            $active = (int) ($mirror->balance_active ?? 0);
            $dim = (int) ($mirror->balance_faded ?? 0);
            $total = $active + $dim;

            $mirror->committed_dim = 0;
            $mirror->balance = $total;
            $mirror->save();

            $sub->balance_active = $active;
            $sub->balance_faded = $dim;
            $sub->balance = $total;
            $sub->save();

            return [
                'sub_account_id' => (int) $sub->id,
                'mirror_account_id' => (int) $mirror->id,
                'active' => $active,
                'dim' => $dim,
                'total' => $total,
            ];
        });
    }

    public function auditAllMainAccounts(): Collection
    {
        return Account::whereIn('type', ['user', 'legal_entity', 'system'])
            ->orderBy('id')
            ->get()
            ->map(fn (Account $account) => $this->audit($account));
    }
}
