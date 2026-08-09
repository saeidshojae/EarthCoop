<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\LedgerEntry;
use Carbon\CarbonInterface;

/**
 * Read-only observation for active-money circulation.
 *
 * This service does not charge tax or move money. It produces a conservative
 * candidate snapshot for policy review: internal Main↔Sub transfers are not
 * treated as circulation, while any external active debit resets the idle clock.
 */
class IdleCapitalObservationService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly AccountBalanceService $balances,
        private readonly TransactionService $transactions,
        private readonly MonetaryPolicyService $policy,
    ) {
    }

    public function observeUser(int $userId, ?CarbonInterface $asOf = null): array
    {
        $main = $this->accounts->getMainAccountForUser($userId);
        if (! $main) {
            throw new \RuntimeException('Najm Bahar main account not found.');
        }

        $asOf = $asOf ? $asOf->copy() : now();
        $periodDays = max(1, (int) $this->policy->parameter('idle_observation_period_days', 180));
        $exemptBalance = max(0, (int) $this->policy->parameter('idle_observation_exempt_balance_gol', 0));
        $cutoff = $asOf->copy()->subDays($periodDays);
        $wallet = $this->balances->aggregate($main);
        $accountIds = $this->transactions->getUserAccountIds($userId);

        $lastExternalActiveDebit = null;
        if ($accountIds !== []) {
            $lastExternalActiveDebit = LedgerEntry::query()
                ->whereIn('account_id', $accountIds)
                ->where('amount', '<', 0)
                ->where(function ($query) {
                    $query->where('meta->balance_type', 'active')
                        ->orWhere('meta->balance_bucket', 'active')
                        ->orWhere('meta->money_state', 'active');
                })
                ->where(function ($query) {
                    $query->whereNull('meta->type')
                        ->orWhere('meta->type', '!=', 'internal_account_transfer');
                })
                ->orderByDesc('created_at')
                ->first()?->created_at;
        }

        $active = (int) $wallet['active'];
        $isCandidate = $active > $exemptBalance
            && ($lastExternalActiveDebit === null || $lastExternalActiveDebit->lt($cutoff));

        return [
            'user_id' => $userId,
            'account_id' => $main->id,
            'as_of' => $asOf,
            'policy_version_id' => $this->policy->versionId(),
            'observation_period_days' => $periodDays,
            'active_balance_gol' => $active,
            'exempt_balance_gol' => $exemptBalance,
            'idle_candidate_gol' => $isCandidate ? max(0, $active - $exemptBalance) : 0,
            'last_external_active_outflow_at' => $lastExternalActiveDebit,
            'candidate_since' => $isCandidate ? ($lastExternalActiveDebit ?? $main->created_at) : null,
            'is_idle_candidate' => $isCandidate,
            'assessment_only' => true,
        ];
    }
}
