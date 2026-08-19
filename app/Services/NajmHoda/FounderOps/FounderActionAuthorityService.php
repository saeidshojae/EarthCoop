<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderActionAuthorityService
{
    public const MODES = [
        'observe',
        'propose',
        'approval_required',
        'delegated_safe',
        'forbidden',
    ];

    /**
     * Return the effective authority decision for one Founder Operations action.
     * No mutation is performed here. Domain command services must call this gate
     * before executing any state-changing operation.
     *
     * @return array<string,mixed>
     */
    public function evaluate(string $domain, string $action, bool $founderApproved = false): array
    {
        $mode = $this->mode($domain, $action);
        $delegated = $mode === 'delegated_safe' && $this->isDelegated($domain, $action);
        $approved = $mode === 'approval_required' && $founderApproved;

        return [
            'domain' => $domain,
            'action' => $action,
            'mode' => $mode,
            'known_action' => $this->isKnown($domain, $action),
            'may_observe' => $mode !== 'forbidden',
            'may_prepare' => in_array($mode, ['propose', 'approval_required', 'delegated_safe'], true),
            'requires_founder_approval' => $mode === 'approval_required' && ! $founderApproved,
            'delegation_enabled' => $delegated,
            'may_execute' => $approved || $delegated,
            'reason' => $this->reason($mode, $founderApproved, $delegated),
        ];
    }

    public function mode(string $domain, string $action): string
    {
        $mode = config("najm-hoda-founder-action-policy.domains.{$domain}.actions.{$action}");
        if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
            return $this->defaultMode();
        }

        return $mode;
    }

    public function isKnown(string $domain, string $action): bool
    {
        return config("najm-hoda-founder-action-policy.domains.{$domain}.actions.{$action}") !== null;
    }

    public function mayExecute(string $domain, string $action, bool $founderApproved = false): bool
    {
        return (bool) $this->evaluate($domain, $action, $founderApproved)['may_execute'];
    }

    /** @return array<string,array<string,string>> */
    public function matrix(): array
    {
        $domains = (array) config('najm-hoda-founder-action-policy.domains', []);
        $matrix = [];

        foreach ($domains as $domain => $definition) {
            if (! is_string($domain) || ! is_array($definition)) continue;
            $actions = (array) ($definition['actions'] ?? []);
            $matrix[$domain] = [];
            foreach ($actions as $action => $mode) {
                if (is_string($action) && is_string($mode) && in_array($mode, self::MODES, true)) {
                    $matrix[$domain][$action] = $mode;
                }
            }
        }

        return $matrix;
    }

    protected function isDelegated(string $domain, string $action): bool
    {
        if (! (bool) config('najm-hoda-founder-action-policy.delegation.enabled', false)) {
            return false;
        }

        $domains = array_values((array) config('najm-hoda-founder-action-policy.delegation.allowed_domains', []));
        $actions = array_values((array) config('najm-hoda-founder-action-policy.delegation.allowed_actions', []));

        $domainAllowed = in_array('*', $domains, true) || in_array($domain, $domains, true);
        $actionRef = $domain . '.' . $action;
        $actionAllowed = in_array('*', $actions, true) || in_array($actionRef, $actions, true);

        return $domainAllowed && $actionAllowed;
    }

    protected function defaultMode(): string
    {
        $mode = (string) config('najm-hoda-founder-action-policy.default_mode', 'forbidden');
        return in_array($mode, self::MODES, true) ? $mode : 'forbidden';
    }

    protected function reason(string $mode, bool $founderApproved, bool $delegated): string
    {
        return match ($mode) {
            'observe' => 'read_only',
            'propose' => 'proposal_only',
            'approval_required' => $founderApproved ? 'founder_approved' : 'awaiting_founder_approval',
            'delegated_safe' => $delegated ? 'explicit_delegation_active' : 'delegation_not_enabled',
            default => 'forbidden_by_policy',
        };
    }
}
