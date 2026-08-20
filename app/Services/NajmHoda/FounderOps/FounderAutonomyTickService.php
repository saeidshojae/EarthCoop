<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class FounderAutonomyTickService
{
    public function __construct(
        protected FounderAutonomyBridgeService $bridge,
        protected FounderActionExecutionService $execution,
        protected FounderLowRiskDomainActionService $handlers,
        protected RuntimeEventBus $events
    ) {}

    public function run(int $hours = 24, int $limit = 12, bool $applyDelegated = true): array
    {
        $plan = $this->bridge->plan($hours, $limit);
        $results = [];

        foreach ((array) ($plan['actions'] ?? []) as $item) {
            if (! is_array($item)) continue;

            $domain = (string) ($item['domain'] ?? '');
            $action = (string) ($item['action'] ?? '');
            $status = (string) data_get($item, 'preparation.status', '');
            $actionContext = is_array($item['action_context'] ?? null) ? $item['action_context'] : [];
            $reasonCode = (string) ($actionContext['reason_code'] ?? data_get($item, 'preparation.decision.reason', ''));

            if ($status !== 'delegated_ready' || ! $applyDelegated) {
                $results[] = ['domain' => $domain, 'action' => $action, 'status' => 'planned_only', 'preparation_status' => $status];
                continue;
            }

            if (! $this->handlers->supports($domain, $action)) {
                $results[] = ['domain' => $domain, 'action' => $action, 'status' => 'not_executed', 'reason' => 'no_canonical_low_risk_handler'];
                continue;
            }

            $result = $this->execution->execute(
                $domain,
                $action,
                fn () => $this->handlers->execute($domain, $action, $actionContext),
                null,
                [
                    'entity_type' => $actionContext['entity_type'] ?? 'founder_attention',
                    'entity_id' => $actionContext['entity_id'] ?? null,
                    'reason_code' => $reasonCode,
                ]
            );
            $results[] = $result;
        }

        $summary = [
            'planned' => count((array) ($plan['actions'] ?? [])),
            'executed' => count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? null) === 'executed')),
            'planned_only' => count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? null) === 'planned_only')),
            'not_executed' => count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? null) === 'not_executed')),
            'blocked' => count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? null) === 'blocked')),
        ];

        $this->events->emit('najm_hoda.founder_ops.autonomy.tick.completed', $summary);

        return ['generated_at' => now()->toIso8601String(), 'plan' => $plan, 'summary' => $summary, 'results' => $results];
    }
}
