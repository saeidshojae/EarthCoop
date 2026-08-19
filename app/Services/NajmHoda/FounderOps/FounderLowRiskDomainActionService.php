<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

/**
 * Canonical dispatcher for the first Founder Ops delegated-safe actions.
 *
 * Keep this service deliberately narrow: adding an action here is an explicit
 * security decision. Unknown actions are never executed.
 */
class FounderLowRiskDomainActionService
{
    public function __construct(
        protected NajmHodaOpsHealthMonitor $health,
        protected RuntimeEventBus $events
    ) {}

    public function supports(string $domain, string $action): bool
    {
        return in_array($domain . '.' . $action, [
            'runtime_health.collect_health_snapshot',
            'runtime_health.run_read_only_diagnostic',
        ], true);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function execute(string $domain, string $action, array $context = []): array
    {
        if (! $this->supports($domain, $action)) {
            return ['success' => false, 'status' => 'unsupported', 'reason' => 'no_canonical_low_risk_handler'];
        }

        $snapshot = $this->health->snapshot();
        $result = [
            'success' => true,
            'status' => 'completed',
            'domain' => $domain,
            'action' => $action,
            'health_status' => (string) ($snapshot['status'] ?? 'unknown'),
            'metrics' => (array) ($snapshot['metrics'] ?? []),
            'generated_at' => (string) ($snapshot['generated_at'] ?? now()->toIso8601String()),
        ];

        $this->events->emit('najm_hoda.founder_ops.low_risk.completed', [
            'domain' => $domain,
            'action' => $action,
            'health_status' => $result['health_status'],
            'reason_code' => is_scalar($context['reason_code'] ?? null) ? (string) $context['reason_code'] : null,
        ]);

        return $result;
    }
}
