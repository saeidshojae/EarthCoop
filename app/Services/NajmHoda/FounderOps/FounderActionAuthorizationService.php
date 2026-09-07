<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class FounderActionAuthorizationService
{
    public function __construct(
        protected FounderActionAuthorityService $authority,
        protected FounderDelegationGrantService $delegations,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected RuntimeEventBus $events
    ) {}

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function authorize(string $domain, string $action, array $context = []): array
    {
        $mode = $this->authority->mode($domain, $action);
        $decision = $this->authority->evaluate($domain, $action);

        if ($mode === 'forbidden') {
            $this->emit('denied', $domain, $action, $mode, $context);
            return $this->result('denied', $decision);
        }

        if (in_array($mode, ['observe', 'propose'], true)) {
            $this->emit($mode, $domain, $action, $mode, $context);
            return $this->result($mode, $decision);
        }

        if ($mode === 'delegated_safe') {
            $granted = $this->delegations->isGranted($domain, $action);
            $outcome = $granted ? 'delegated_authorized' : 'delegation_required';
            $this->emit($outcome, $domain, $action, $mode, $context);
            return [
                'status' => $outcome,
                'executable' => $granted,
                'decision' => $this->authority->evaluate($domain, $action, false, $granted),
                'approval_request' => null,
            ];
        }

        $request = $this->approvals->requestApproval([
            'action' => 'founder_ops:' . $domain . ':' . $action,
            'risk' => $this->riskForDomain($domain),
            'mode' => 'approval_required',
            'domain' => $domain,
            'capability_action' => $action,
        ], $this->safeContext($context));

        $this->emit('approval_requested', $domain, $action, $mode, ['approval_request_id' => $request['id'] ?? null]);

        return [
            'status' => 'approval_required',
            'executable' => false,
            'decision' => $decision,
            'approval_request' => $request,
        ];
    }

    public function mayExecute(string $domain, string $action, bool $founderApproved = false): bool
    {
        $delegated = $this->authority->mode($domain, $action) === 'delegated_safe'
            && $this->delegations->isGranted($domain, $action);

        return $this->authority->mayExecute($domain, $action, $founderApproved, $delegated);
    }

    protected function result(string $status, array $decision): array
    {
        return ['status' => $status, 'executable' => false, 'decision' => $decision, 'approval_request' => null];
    }

    protected function emit(string $outcome, string $domain, string $action, string $mode, array $context): void
    {
        $this->events->emit('najm_hoda.founder.authorization.' . $outcome, [
            'domain' => $domain,
            'action' => $action,
            'mode' => $mode,
            'scope' => 'founder_operations',
            'context_keys' => array_values(array_map('strval', array_keys($context))),
        ]);
    }

    protected function riskForDomain(string $domain): string
    {
        $risk = data_get(config('najm-hoda-founder-operations.domains', []), $domain . '.risk', 'high');
        return is_string($risk) && $risk !== '' ? $risk : 'high';
    }

    protected function safeContext(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (preg_match('/(body|content|message|secret|token|password|payload|email|phone|note|reason)/i', $key)) continue;
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
                continue;
            }
            if (is_string($value)) $safe[$key] = mb_substr($value, 0, 180);
        }
        return $safe;
    }
}
