<?php

namespace App\Services;

use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    public function addPoints(User $user, int $delta, string $action, array $meta = [], $referenceId = null, $source = null, string $dimension = 'participation', bool $convertible = false)
    {
        return DB::transaction(function () use ($user, $delta, $action, $meta, $referenceId, $source, $dimension, $convertible) {
            $point = UserPoint::firstOrCreate(['user_id' => $user->id], ['points' => 0]);
            $newBalance = $point->points + $delta;
            $point->points = $newBalance;
            $point->level = $this->determineLevel($newBalance);
            $point->save();

            return UserPointTransaction::create([
                'user_id' => $user->id, 'delta' => $delta, 'balance_after' => $newBalance,
                'action' => $action, 'dimension' => $dimension, 'convertible' => $convertible,
                'source' => $source, 'reference_id' => $referenceId, 'metadata' => $meta,
            ]);
        });
    }

    public function applyAction(User $user, string $actionKey, array $meta = [], $referenceId = null, $source = null)
    {
        $rule = ReputationRule::where('key', $actionKey)->first();
        if ($rule) {
            if (! $rule->active) return null;
            $weight = (int) $rule->weight;
            $dailyCap = $rule->daily_cap !== null ? (int) $rule->daily_cap : null;
            $dimension = $rule->dimension ?: 'participation';
            $convertible = (bool) $rule->convertible;
        } else {
            $weight = (int) config("reputation.weights.{$actionKey}", 0);
            $configuredCap = config("reputation.daily_caps.{$actionKey}");
            $dailyCap = $configuredCap !== null ? (int) $configuredCap : null;
            $dimension = (string) config("reputation.dimensions.{$actionKey}", 'participation');
            $convertible = (bool) config("reputation.convertible.{$actionKey}", false);
        }
        if ($weight === 0) return null;

        if ($weight > 0 && $dailyCap !== null) {
            $already = (int) UserPointTransaction::where('user_id', $user->id)->where('action', $actionKey)
                ->where('created_at', '>=', now()->subDay())->where('delta', '>', 0)->sum('delta');
            $remaining = $dailyCap - $already;
            if ($remaining <= 0) return null;
            $award = min($weight, $remaining);
            return $this->addPoints($user, $award, $actionKey, array_merge($meta, [
                'capped_award' => $award, 'cap' => $dailyCap, 'already_awarded' => $already,
            ]), $referenceId, $source, $dimension, $convertible);
        }

        return $this->addPoints($user, $weight, $actionKey, $meta, $referenceId, $source, $dimension, $convertible);
    }

    public function getPoints(User $user): int
    {
        return optional(UserPoint::where('user_id', $user->id)->first())->points ?? 0;
    }

    public function determineLevel(int $points): ?string
    {
        $level = null;
        foreach (config('reputation.tiers', []) as $name => $threshold) if ($points >= $threshold) $level = $name;
        return $level;
    }
}
