<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderActionRequestService
{
    public function __construct(
        protected FounderActionAuthorityService $authority,
        protected FounderManagedDomainRegistry $domains,
        protected FounderDelegationGrantService $delegations,
        protected NajmHodaAutonomyApprovalService $approvals
    ) {}

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function prepare(string $domain, string $action, array $context = []): array
    {
        $mode = $this->authority->mode($domain, $action);
        $delegated = $mode === 'delegated_safe' && $this->delegations->isGranted($domain, $action);
        $decision = $this->authority->evaluate($domain, $action, false, $delegated);
        $risk = (string) data_get($this->domains->get($domain), 'risk', 'unknown');

        if ($decision['mode'] === 'forbidden') {
            return ['status' => 'blocked', 'decision' => $decision, 'approval_request' => null];
        }

        if ($decision['mode'] === 'approval_required') {
            $safeContext = $this->safeContext($context);
            $existing = $this->findPending($domain, $action, (string) ($safeContext['reason_code'] ?? ''));
            if ($existing !== null) {
                return ['status' => 'awaiting_approval', 'decision' => $decision, 'approval_request' => $existing, 'deduplicated' => true];
            }

            $request = $this->approvals->requestApproval([
                'action' => 'founder_ops:' . $domain . '.' . $action,
                'domain' => $domain,
                'domain_action' => $action,
                'risk' => $risk,
                'mode' => 'approval_required',
                'execution_contract' => 'founder_operations',
            ], $safeContext);

            return ['status' => 'awaiting_approval', 'decision' => $decision, 'approval_request' => $request, 'deduplicated' => false];
        }

        if ($decision['mode'] === 'delegated_safe') {
            return [
                'status' => $decision['may_execute'] ? 'delegated_ready' : 'delegation_required',
                'decision' => $decision,
                'approval_request' => null,
            ];
        }

        return [
            'status' => $decision['mode'] === 'propose' ? 'proposal_only' : 'read_only',
            'decision' => $decision,
            'approval_request' => null,
        ];
    }

    /** @return array<string,mixed>|null */
    protected function findPending(string $domain, string $action, string $reasonCode): ?array
    {
        foreach ($this->approvals->pending(200) as $request) {
            if ((string) data_get($request, 'plan_item.domain') !== $domain) continue;
            if ((string) data_get($request, 'plan_item.domain_action') !== $action) continue;
            if ($reasonCode !== '' && (string) data_get($request, 'context.reason_code') !== $reasonCode) continue;
            return $request;
        }
        return null;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    protected function safeContext(array $context): array
    {
        $allowed = [
            'entity_type', 'entity_id', 'source_event', 'attention_priority',
            'reason_code', 'requested_by', 'correlation_id', 'review_draft_id',
        ];

        return collect($context)
            ->only($allowed)
            ->filter(static fn ($value): bool => is_null($value) || is_scalar($value))
            ->all();
    }
}
