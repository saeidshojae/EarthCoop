<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAutonomyCostLedgerService
{
    protected string $dailyLedgerPrefix = 'najm_hoda:autonomy:cost:ledger:daily:';
    protected string $dailyTotalPrefix = 'najm_hoda:autonomy:cost:total:daily:';
    protected string $monthlyTotalPrefix = 'najm_hoda:autonomy:cost:total:monthly:';

    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function record(string $action, float $amount, array $meta = []): array
    {
        $amount = max(0.0, round($amount, 6));
        $dayKey = now()->format('Ymd');
        $monthKey = now()->format('Ym');

        $entry = [
            'action' => trim($action),
            'amount' => $amount,
            'meta' => $meta,
            'recorded_at' => now()->toIso8601String(),
        ];

        $ledgerKey = $this->dailyLedgerPrefix . $dayKey;
        $ledger = Cache::get($ledgerKey, []);
        if (!is_array($ledger)) {
            $ledger = [];
        }
        array_unshift($ledger, $entry);
        $maxEntries = max(100, (int) config('najm-hoda.runtime.autonomy.costs.max_daily_ledger_entries', 2000));
        $ledger = array_slice($ledger, 0, $maxEntries);
        Cache::put($ledgerKey, $ledger, now()->addDays(40));

        $dailyTotalKey = $this->dailyTotalPrefix . $dayKey;
        $monthlyTotalKey = $this->monthlyTotalPrefix . $monthKey;
        $daily = round(((float) Cache::get($dailyTotalKey, 0.0)) + $amount, 6);
        $monthly = round(((float) Cache::get($monthlyTotalKey, 0.0)) + $amount, 6);
        Cache::put($dailyTotalKey, $daily, now()->addDays(40));
        Cache::put($monthlyTotalKey, $monthly, now()->addDays(400));

        $payload = [
            'action' => $entry['action'],
            'amount' => $amount,
            'daily_total' => $daily,
            'monthly_total' => $monthly,
        ];
        $this->eventBus->emit('najm_hoda.autonomy.cost.recorded', $payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function canSpend(float $amount): array
    {
        $amount = max(0.0, round($amount, 6));
        $status = $this->status();

        $dailyProjected = (float) ($status['daily_total'] ?? 0.0) + $amount;
        $monthlyProjected = (float) ($status['monthly_total'] ?? 0.0) + $amount;
        $dailyBudget = (float) ($status['daily_budget'] ?? 0.0);
        $monthlyBudget = (float) ($status['monthly_budget'] ?? 0.0);

        if ($dailyBudget > 0 && $dailyProjected > $dailyBudget) {
            $this->eventBus->emit('najm_hoda.autonomy.cost.blocked', [
                'reason' => 'daily_budget_exceeded',
                'projected' => $dailyProjected,
                'budget' => $dailyBudget,
            ]);
            return ['allowed' => false, 'reason' => 'daily_budget_exceeded'];
        }

        if ($monthlyBudget > 0 && $monthlyProjected > $monthlyBudget) {
            $this->eventBus->emit('najm_hoda.autonomy.cost.blocked', [
                'reason' => 'monthly_budget_exceeded',
                'projected' => $monthlyProjected,
                'budget' => $monthlyBudget,
            ]);
            return ['allowed' => false, 'reason' => 'monthly_budget_exceeded'];
        }

        return ['allowed' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $dayKey = now()->format('Ymd');
        $monthKey = now()->format('Ym');

        $daily = (float) Cache::get($this->dailyTotalPrefix . $dayKey, 0.0);
        $monthly = (float) Cache::get($this->monthlyTotalPrefix . $monthKey, 0.0);
        $dailyBudget = (float) config('najm-hoda.runtime.autonomy.costs.daily_budget', 0.0);
        $monthlyBudget = (float) config('najm-hoda.runtime.autonomy.costs.monthly_budget', 0.0);

        return [
            'daily_total' => round($daily, 6),
            'monthly_total' => round($monthly, 6),
            'daily_budget' => $dailyBudget,
            'monthly_budget' => $monthlyBudget,
            'day' => $dayKey,
            'month' => $monthKey,
        ];
    }

    public function estimateForAction(string $action): float
    {
        $action = trim($action);
        $default = (float) config('najm-hoda.runtime.autonomy.costs.default_action_cost', 0.001);
        $estimated = (float) config("najm-hoda.runtime.autonomy.costs.action_estimates.{$action}", $default);
        return max(0.0, round($estimated, 6));
    }
}
