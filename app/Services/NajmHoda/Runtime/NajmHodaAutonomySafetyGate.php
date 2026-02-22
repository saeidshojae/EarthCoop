<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAutonomySafetyGate
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<string, mixed> $planItem
     * @param array<int, string> $goals
     * @return array<string, mixed>
     */
    public function evaluate(array $planItem, array $goals, int $acceptedCount): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.safety.enabled', true)) {
            return ['allowed' => true];
        }

        $action = (string) ($planItem['action'] ?? '');
        $risk = (string) ($planItem['risk'] ?? 'low');

        $override = $this->adaptiveOverride();
        $maxActionsBase = (int) config('najm-hoda.runtime.autonomy.safety.max_actions_per_run', 3);
        $maxActionsOverride = (int) ($override['max_actions_per_run'] ?? 0);
        $maxActions = max(1, $maxActionsOverride > 0 ? $maxActionsOverride : $maxActionsBase);
        if ($acceptedCount >= $maxActions) {
            return $this->deny($action, 'budget_exceeded', ['max_actions_per_run' => $maxActions]);
        }

        $allowedRisk = config('najm-hoda.runtime.autonomy.safety.allowed_risk_levels', ['low']);
        $allowedRisk = is_array($allowedRisk) ? array_map(static fn ($v): string => (string) $v, $allowedRisk) : ['low'];
        if (($override['allow_apply_low_risk'] ?? null) === false) {
            $allowedRisk = ['low'];
        }
        if (!in_array($risk, $allowedRisk, true)) {
            return $this->deny($action, 'risk_not_allowed', ['risk' => $risk]);
        }

        $blockedActions = config('najm-hoda.runtime.autonomy.safety.blocked_actions', []);
        $blockedActions = is_array($blockedActions) ? array_map(static fn ($v): string => (string) $v, $blockedActions) : [];
        if (in_array($action, $blockedActions, true)) {
            return $this->deny($action, 'action_blocked');
        }

        $allowedActions = config('najm-hoda.runtime.autonomy.safety.allowed_actions', []);
        $allowedActions = is_array($allowedActions) ? array_map(static fn ($v): string => (string) $v, $allowedActions) : [];
        if (!empty($allowedActions) && !in_array($action, $allowedActions, true)) {
            return $this->deny($action, 'action_not_in_allowlist');
        }

        $scopeMap = config('najm-hoda.runtime.autonomy.safety.action_goal_scope', []);
        if (is_array($scopeMap) && isset($scopeMap[$action]) && is_array($scopeMap[$action])) {
            $requiredGoals = array_values(array_map(static fn ($v): string => (string) $v, $scopeMap[$action]));
            $matched = array_values(array_intersect($requiredGoals, $goals));
            if (empty($matched)) {
                return $this->deny($action, 'scope_goal_mismatch', [
                    'required_goals' => $requiredGoals,
                ]);
            }
        }

        $this->eventBus->emit('najm_hoda.autonomy.safety.approved', [
            'action' => $action,
            'risk' => $risk,
        ]);

        return ['allowed' => true];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function deny(string $action, string $reason, array $context = []): array
    {
        $this->eventBus->emit('najm_hoda.autonomy.safety.blocked', array_merge([
            'action' => $action,
            'reason' => $reason,
        ], $context));

        return [
            'allowed' => false,
            'reason' => $reason,
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function adaptiveOverride(): array
    {
        $override = Cache::get('najm_hoda:autonomy:adaptive_safety_override');
        if (!is_array($override)) {
            return [];
        }

        $expiresAt = (string) ($override['expires_at'] ?? '');
        if ($expiresAt === '') {
            return $override;
        }

        try {
            if (now()->greaterThan(\Carbon\CarbonImmutable::parse($expiresAt))) {
                Cache::forget('najm_hoda:autonomy:adaptive_safety_override');
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return $override;
    }
}
