<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class FounderActionAuthorizationService
{
    public function __construct(
        protected FounderCapabilityGate $gate,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected RuntimeEventBus $events
    ) {}

    /**
     * Convert a proposed Founder Ops action into one of four outcomes:
     * read/proposal only, waiting for explicit founder approval, waiting for an
     * explicit delegation grant, or denied. This service never executes the
     * domain mutation itself.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function authorize(string $domain, string $action, array $context = []): array
    {
        $decision = $this->gate->inspect($domain, $action);
        $level = (string) $decision['level'];

        if ($level === FounderCapabilityGate::FORBIDDEN) {
            $this->emit('denied', $domain, $action, $decision, $context);
            return [
                'status' => 'denied',
                'executable' => false,
                'decision' => $decision,
                'approval_request' => null,
            ];
        }

        if ($level === FounderCapabilityGate::OBSERVE || $level === FounderCapabilityGate::PROPOSE) {
            $this->emit($level, $domain, $action, $decision, $context);
            return [
                'status' => $level,
                'executable' => false,
                'decision' => $decision,
                'approval_request' => null,
            ];
        }

        if ($level === FounderCapabilityGate::DELEGATED_SAFE) {
            $this->emit('delegation_required', $domain, $action, $decision, $context);
            return [
                'status' => 'delegation_required',
                'executable' => false,
                'decision' => $decision,
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

        $this->emit('approval_requested', $domain, $action, $decision, [
            'approval_request_id' => $request['id'] ?? null,
        ]);

        return [
            'status' => 'approval_required',
            'executable' => false,
            'decision' => $decision,
            'approval_request' => $request,
        ];
    }

    /**
     * Re-check an action immediately before a mutation executor is called.
     * This deliberately requires the caller to prove approval/delegation state;
     * no approval request is treated as execution permission by itself.
     */
    public function mayExecute(
        string $domain,
        string $action,
        bool $founderApproved = false,
        bool $delegationGranted = false
    ): bool {
        return $this->gate->canExecute($domain, $action, $founderApproved, $delegationGranted);
    }

    /** @param array<string,mixed> $decision @param array<string,mixed> $context */
    protected function emit(string $outcome, string $domain, string $action, array $decision, array $context): void
    {
        $this->events->emit('najm_hoda.founder.authorization.' . $outcome, [
            'domain' => $domain,
            'action' => $action,
            'level' => $decision['level'] ?? FounderCapabilityGate::FORBIDDEN,
            'scope' => 'founder_operations',
            'context_keys' => array_values(array_map('strval', array_keys($context))),
        ]);
    }

    protected function riskForDomain(string $domain): string
    {
        $risk = data_get(config('najm-hoda-founder-operations.domains', []), $domain . '.risk', 'high');
        return is_string($risk) && $risk !== '' ? $risk : 'high';
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    protected function safeContext(array $context): array
    {
        // Approval queue stores context. Keep only identifiers and bounded scalar
        // metadata; never copy bodies, messages, secrets or financial payloads.
        $safe = [];
        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (preg_match('/(body|content|message|secret|token|password|payload|email|phone|note|reason)/i', $key)) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
                continue;
            }
            if (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, 180);
            }
        }
        return $safe;
    }
}
