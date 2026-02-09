<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    public function addPoints(User $user, int $delta, string $action, array $meta = [], $referenceId = null, $source = null)
    {
        return DB::transaction(function () use ($user, $delta, $action, $meta, $referenceId, $source) {
            $point = UserPoint::firstOrCreate([
                'user_id' => $user->id,
            ], [
                'points' => 0,
            ]);

            $newBalance = $point->points + $delta;
            $point->points = $newBalance;
            $point->level = $this->determineLevel($newBalance);
            $point->save();

            $tx = UserPointTransaction::create([
                'user_id' => $user->id,
                'delta' => $delta,
                'balance_after' => $newBalance,
                'action' => $action,
                'source' => $source,
                'reference_id' => $referenceId,
                'metadata' => $meta,
            ]);

            return $tx;
        });
    }

    /**
     * Apply a configured action key: read weight from DB (reputation_rules) or config and apply.
     */
    public function applyAction(User $user, string $actionKey, array $meta = [], $referenceId = null, $source = null)
    {
        // Prefer DB rule if exists
        $rule = \App\Models\ReputationRule::where('key', $actionKey)->where('active', true)->first();
        if ($rule) {
            $weight = (int)$rule->weight;
        } else {
            $weight = (int)config("reputation.weights.{$actionKey}", 0);
        }

        if ($weight === 0) {
            return null; // nothing to do
        }

        // Enforce daily caps for positive-weight actions
        $dailyCaps = config('reputation.daily_caps', []);
        if ($weight > 0 && isset($dailyCaps[$actionKey])) {
            $cap = (int)$dailyCaps[$actionKey];

            // sum of positive deltas for this action in the last 24 hours
            $since = now()->subDay();
            $already = (int) UserPointTransaction::where('user_id', $user->id)
                ->where('action', $actionKey)
                ->where('created_at', '>=', $since)
                ->where('delta', '>', 0)
                ->sum('delta');

            $remaining = $cap - $already;
            if ($remaining <= 0) {
                return null; // cap exhausted
            }

            // If the configured weight exceeds remaining cap, award only the remainder
            $award = min($weight, $remaining);
            return $this->addPoints($user, $award, $actionKey, array_merge($meta, ['capped_award' => $award, 'cap' => $cap, 'already_awarded' => $already]), $referenceId, $source);
        }

        return $this->addPoints($user, $weight, $actionKey, $meta, $referenceId, $source);
    }

    public function getPoints(User $user): int
    {
        return optional(UserPoint::where('user_id', $user->id)->first())->points ?? 0;
    }

    public function determineLevel(int $points): ?string
    {
        $tiers = config('reputation.tiers', []);
        $level = null;
        foreach ($tiers as $name => $threshold) {
            if ($points >= $threshold) {
                $level = $name;
            }
        }
        return $level;
    }
}
