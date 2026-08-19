<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Throwable;

class FounderActionExecutionService
{
    public function __construct(
        protected FounderActionAuthorityService $authority,
        protected FounderApprovalVerifierService $approvalVerifier,
        protected RuntimeEventBus $events
    ) {}

    /**
     * Execute a domain mutation only after centralized authority validation.
     * The callback must itself call the domain's canonical command/service layer.
     *
     * @param callable():mixed $callback
     * @param array<string,mixed> $auditContext
     * @return array<string,mixed>
     */
    public function execute(
        string $domain,
        string $action,
        callable $callback,
        ?string $approvalRequestId = null,
        array $auditContext = []
    ): array {
        $mode = $this->authority->mode($domain, $action);
        $approval = null;

        if ($mode === 'approval_required') {
            if ($approvalRequestId === null || trim($approvalRequestId) === '') {
                return $this->blocked($domain, $action, 'missing_approval_request');
            }

            $approval = $this->approvalVerifier->verify($approvalRequestId, $domain, $action);
            if (! (bool) ($approval['valid'] ?? false)) {
                return $this->blocked($domain, $action, (string) ($approval['reason'] ?? 'invalid_approval'));
            }
        }

        $decision = $this->authority->evaluate($domain, $action, $mode === 'approval_required');
        if (! (bool) ($decision['may_execute'] ?? false)) {
            return $this->blocked($domain, $action, (string) ($decision['reason'] ?? 'not_authorized'));
        }

        $safeAudit = $this->safeAuditContext($auditContext);
        $this->events->emit('najm_hoda.founder_ops.execution.started', [
            'domain' => $domain,
            'action' => $action,
            'mode' => $mode,
            'approval_request_id' => $approvalRequestId,
            'context' => $safeAudit,
        ]);

        try {
            $result = $callback();
            $this->events->emit('najm_hoda.founder_ops.execution.completed', [
                'domain' => $domain,
                'action' => $action,
                'mode' => $mode,
                'approval_request_id' => $approvalRequestId,
                'context' => $safeAudit,
            ]);

            return [
                'success' => true,
                'status' => 'executed',
                'domain' => $domain,
                'action' => $action,
                'mode' => $mode,
                'approval' => $approval,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            $this->events->emit('najm_hoda.founder_ops.execution.failed', [
                'domain' => $domain,
                'action' => $action,
                'mode' => $mode,
                'approval_request_id' => $approvalRequestId,
                'exception_class' => $e::class,
                'context' => $safeAudit,
            ]);
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    protected function blocked(string $domain, string $action, string $reason): array
    {
        $this->events->emit('najm_hoda.founder_ops.execution.blocked', [
            'domain' => $domain,
            'action' => $action,
            'reason' => $reason,
        ]);

        return [
            'success' => false,
            'status' => 'blocked',
            'domain' => $domain,
            'action' => $action,
            'reason' => $reason,
        ];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    protected function safeAuditContext(array $context): array
    {
        return collect($context)->only([
            'entity_type', 'entity_id', 'correlation_id', 'requested_by', 'reason_code',
        ])->filter(static fn ($value): bool => is_null($value) || is_scalar($value))->all();
    }
}
