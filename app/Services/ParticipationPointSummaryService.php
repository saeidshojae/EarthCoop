<?php

namespace App\Services;

use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use Illuminate\Database\Eloquent\Builder;

class ParticipationPointSummaryService
{
    public function convertibleTransactionsQuery(int $userId): Builder
    {
        return UserPointTransaction::query()
            ->where('user_id', $userId)
            ->where('delta', '>', 0)
            ->where('convertible', true)
            ->where('dimension', 'participation')
            ->where('is_cashed', false)
            ->withSum('consumptions as consumptions_sum_points_consumed', 'points_consumed')
            ->orderBy('created_at', 'asc');
    }

    public function participationReversalPoints(int $userId): int
    {
        return abs((int) UserPointTransaction::query()
            ->where('user_id', $userId)
            ->where('delta', '<', 0)
            ->where('convertible', true)
            ->where('dimension', 'participation')
            ->sum('delta'));
    }

    public function legacyCashedPoints(int $userId): int
    {
        return (int) UserPointTransaction::query()
            ->where('user_id', $userId)
            ->where('delta', '>', 0)
            ->where('convertible', true)
            ->where('dimension', 'participation')
            ->where('is_cashed', true)
            ->sum('delta');
    }

    public function forUser(int $userId): array
    {
        $userPoint = UserPoint::query()->where('user_id', $userId)->first();
        $transactions = $this->convertibleTransactionsQuery($userId)->get();

        $convertibleAwarded = (int) $transactions->sum('delta');
        $ledgerConsumed = (int) $transactions->sum(
            fn ($tx) => (int) ($tx->consumptions_sum_points_consumed ?? 0)
        );
        $reversals = $this->participationReversalPoints($userId);
        $legacyCashed = $this->legacyCashedPoints($userId);
        $remaining = max(0, $convertibleAwarded - $reversals - $ledgerConsumed);

        return [
            'total_points' => (int) ($userPoint?->points ?? 0),
            'level' => (string) ($userPoint?->level ?? 'Bronze'),
            'convertible_awarded_points' => $convertibleAwarded,
            'ledger_consumed_points' => $ledgerConsumed,
            'legacy_cashed_points' => $legacyCashed,
            'participation_reversal_points' => $reversals,
            'cashed_points' => $legacyCashed + $ledgerConsumed,
            'remaining_convertible_points' => $remaining,
            // Compatibility alias for existing API/UI contracts during R6 migration.
            'uncashed_points' => $remaining,
        ];
    }
}
