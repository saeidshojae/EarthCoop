<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LocationGovernanceNajmHodaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $policy = require config_path('najm-hoda-location-governance.php');
        $action = (string) ($policy['allowed_action'] ?? 'review_location_governance_proposal');

        config()->set(
            "najm-hoda.runtime.autonomy.capabilities.{$action}",
            (array) ($policy['capability'] ?? [])
        );

        $allowedActions = (array) config('najm-hoda.runtime.autonomy.safety.allowed_actions', []);
        $allowedActions[] = $action;
        config()->set(
            'najm-hoda.runtime.autonomy.safety.allowed_actions',
            array_values(array_unique(array_map('strval', $allowedActions)))
        );

        $blockedActions = array_merge(
            (array) config('najm-hoda.runtime.autonomy.safety.blocked_actions', []),
            (array) ($policy['blocked_sensitive_actions'] ?? [])
        );
        config()->set(
            'najm-hoda.runtime.autonomy.safety.blocked_actions',
            array_values(array_unique(array_map('strval', $blockedActions)))
        );

        $scopeMap = (array) config('najm-hoda.runtime.autonomy.safety.action_goal_scope', []);
        $scopeMap[$action] = (array) ($policy['goal_scope'] ?? ['review_location_governance']);
        config()->set('najm-hoda.runtime.autonomy.safety.action_goal_scope', $scopeMap);
    }
}
