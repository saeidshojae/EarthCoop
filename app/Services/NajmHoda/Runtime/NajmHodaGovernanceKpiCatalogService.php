<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaGovernanceKpiCatalogService
{
    /**
     * @return array<string, mixed>
     */
    public function baseline(): array
    {
        $catalog = config('najm-hoda.runtime.autonomy.governance.kpis', []);
        if (!is_array($catalog)) {
            $catalog = [];
        }

        $defaults = [
            'auto_action_success_rate' => [
                'label' => 'Auto Action Success Rate',
                'formula' => 'executed_success / (executed_success + executed_failed)',
                'window' => '24h',
                'target_min' => 0.95,
                'warning_below' => 0.9,
                'unit' => 'ratio',
            ],
            'autonomy_coverage_rate' => [
                'label' => 'Autonomy Coverage',
                'formula' => 'automated_daily_operations / total_daily_operations',
                'window' => '24h',
                'target_min' => 0.60,
                'warning_below' => 0.50,
                'unit' => 'ratio',
            ],
            'mttr_reduction_rate' => [
                'label' => 'MTTR Reduction',
                'formula' => '(baseline_mttr - current_mttr) / baseline_mttr',
                'window' => '7d',
                'target_min' => 0.30,
                'warning_below' => 0.20,
                'unit' => 'ratio',
            ],
            'rollback_unwanted_rate' => [
                'label' => 'Unwanted Rollback Rate',
                'formula' => 'unwanted_rollbacks / total_rollbacks',
                'window' => '7d',
                'target_max' => 0.02,
                'warning_above' => 0.03,
                'unit' => 'ratio',
            ],
            'user_satisfaction_score' => [
                'label' => 'User Satisfaction',
                'formula' => 'avg_user_satisfaction_score',
                'window' => '30d',
                'target_min' => 0.80,
                'warning_below' => 0.75,
                'unit' => 'ratio',
            ],
            'human_approval_latency_minutes' => [
                'label' => 'Human Approval Latency',
                'formula' => 'avg(decision_at - requested_at)',
                'window' => '24h',
                'target_max' => 30,
                'warning_above' => 45,
                'unit' => 'minutes',
            ],
            'policy_drift_rate' => [
                'label' => 'Policy Drift Rate',
                'formula' => 'policy_drift_events / total_decisions',
                'window' => '24h',
                'target_max' => 0.01,
                'warning_above' => 0.02,
                'unit' => 'ratio',
            ],
        ];

        return array_replace_recursive($defaults, $catalog);
    }
}
