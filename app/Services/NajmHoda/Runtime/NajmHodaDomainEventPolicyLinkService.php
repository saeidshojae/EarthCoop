<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaDomainEventPolicyLinkService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaAutonomyApprovalService $approvalService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function ingest(string $event, array $payload = []): void
    {
        if (!(bool) config('najm-hoda.runtime.domain_policy_link.enabled', true)) {
            return;
        }

        $domain = $this->resolveDomain($event);
        if ($domain === null) {
            return;
        }

        $outcome = $this->resolveOutcome($event);
        if ($outcome === null) {
            return;
        }

        $risk = (string) ($payload['risk'] ?? 'medium');
        $scope = (string) ($payload['scope'] ?? $this->defaultScope($domain));

        if (in_array($outcome, ['failed', 'rejected'], true)) {
            $this->eventBus->emit('najm_hoda.autonomy.safety.blocked', [
                'action' => $event,
                'reason' => 'domain_service_' . $outcome,
                'domain' => $domain,
                'risk' => $risk,
                'scope' => $scope,
            ]);

            $severity = $risk === 'high' ? 'critical' : 'warning';
            $this->eventBus->emit('najm_hoda.autonomy.governance.alert.raised', [
                'type' => 'domain_service_' . $outcome,
                'severity' => $severity,
                'kpi' => 'domain_failure_rate',
                'value' => 1,
                'threshold' => 0,
                'source' => $domain . '.service_hooks',
                'message' => 'Domain service event ' . $event . ' signaled ' . $outcome,
                'domain' => $domain,
            ]);

            if ($this->shouldRequestApproval($risk)) {
                $this->approvalService->requestApproval([
                    'action' => 'review_' . $domain . '_service_event',
                    'risk' => $risk,
                    'mode' => 'propose',
                    'input' => [
                        'event' => $event,
                        'outcome' => $outcome,
                    ],
                ], [
                    'source' => 'domain_policy_link',
                    'domain' => $domain,
                    'event' => $event,
                ]);
            }
        }

        if ($domain === 'content' && in_array($outcome, ['deleted'], true)) {
            $severity = $risk === 'high' ? 'critical' : 'warning';
            $this->eventBus->emit('najm_hoda.autonomy.governance.alert.raised', [
                'type' => 'domain_sensitive_mutation',
                'severity' => $severity,
                'kpi' => 'content_mutation_rate',
                'value' => 1,
                'threshold' => 0,
                'source' => 'content.service_hooks',
                'message' => 'Content mutation observed on ' . $event,
                'domain' => $domain,
            ]);

            if ($this->shouldRequestApproval($risk)) {
                $this->approvalService->requestApproval([
                    'action' => 'review_content_service_event',
                    'risk' => $risk,
                    'mode' => 'propose',
                    'input' => [
                        'event' => $event,
                        'outcome' => $outcome,
                    ],
                ], [
                    'source' => 'domain_policy_link',
                    'domain' => $domain,
                    'event' => $event,
                ]);
            }
        }
    }

    protected function resolveOutcome(string $event): ?string
    {
        foreach (['failed', 'rejected', 'succeeded', 'requested', 'created', 'updated', 'deleted'] as $suffix) {
            if (str_ends_with($event, '.' . $suffix)) {
                return $suffix;
            }
        }

        return null;
    }

    protected function resolveDomain(string $event): ?string
    {
        $prefixes = [
            'najm_hoda.input.najm_bahar.service.' => 'najm_bahar',
            'najm_hoda.input.support.service.' => 'support',
            'najm_hoda.input.auth.service.' => 'auth',
            'najm_hoda.input.content.service.' => 'content',
        ];

        foreach ($prefixes as $prefix => $domain) {
            if (str_starts_with($event, $prefix)) {
                return $domain;
            }
        }

        return null;
    }

    protected function defaultScope(string $domain): string
    {
        return match ($domain) {
            'najm_bahar' => 'economy:najm-bahar',
            'support' => 'support',
            'auth' => 'auth',
            'content' => 'content',
            default => 'system',
        };
    }

    protected function shouldRequestApproval(string $risk): bool
    {
        if (!(bool) config('najm-hoda.runtime.domain_policy_link.request_approval_on_failures', true)) {
            return false;
        }

        $levels = config('najm-hoda.runtime.domain_policy_link.approval_risk_levels', ['medium', 'high']);
        $levels = is_array($levels) ? array_map(static fn ($v): string => (string) $v, $levels) : ['medium', 'high'];

        return in_array($risk, $levels, true);
    }
}
