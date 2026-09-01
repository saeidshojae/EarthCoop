<?php

namespace App\Services;

use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use Illuminate\Database\Eloquent\Builder;

class ParticipationPointSummaryService
{
    private const REPUTATION_DIMENSIONS = [
        'participation',
        'reliability',
        'expertise',
        'civic_trust',
    ];

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

    /**
     * Signed reputation totals by the dimension snapshot stored on each ledger event.
     * Unknown/null historical dimensions stay explicit instead of being guessed into
     * one of the modern dimensions.
     */
    public function reputationBreakdown(int $userId): array
    {
        $breakdown = [
            'participation' => 0,
            'reliability' => 0,
            'expertise' => 0,
            'civic_trust' => 0,
            'legacy_other' => 0,
        ];

        $totals = UserPointTransaction::query()
            ->where('user_id', $userId)
            ->selectRaw('dimension, COALESCE(SUM(delta), 0) as points')
            ->groupBy('dimension')
            ->get();

        foreach ($totals as $total) {
            $dimension = (string) ($total->dimension ?? '');
            $key = in_array($dimension, self::REPUTATION_DIMENSIONS, true)
                ? $dimension
                : 'legacy_other';

            $breakdown[$key] += (int) $total->points;
        }

        return $breakdown;
    }

    /**
     * Deliberately public-safe summary. Conversion eligibility/consumption fields
     * never cross the public-profile data boundary.
     */
    public function publicReputationSummary(int $userId): array
    {
        $userPoint = UserPoint::query()->where('user_id', $userId)->first();

        return [
            'total_points' => (int) ($userPoint?->points ?? 0),
            'level' => (string) ($userPoint?->level ?? 'Bronze'),
            'level_label' => $this->levelLabel((string) ($userPoint?->level ?? 'Bronze')),
            'reputation_breakdown' => $this->reputationBreakdown($userId),
        ];
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
            'level_label' => $this->levelLabel((string) ($userPoint?->level ?? 'Bronze')),
            'reputation_breakdown' => $this->reputationBreakdown($userId),
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

    private function levelLabel(string $level): string
    {
        return match (strtolower(trim($level))) {
            'bronze' => 'برنزی',
            'silver' => 'نقره‌ای',
            'gold' => 'طلایی',
            'platinum' => 'پلاتینی',
            'diamond' => 'الماسی',
            default => $level !== '' ? $level : 'برنزی',
        };
    }
}
