<?php

namespace App\Services\NajmHoda\FounderOps;

use InvalidArgumentException;

class FounderCapabilityGate
{
    public const OBSERVE = 'observe';
    public const PROPOSE = 'propose';
    public const APPROVAL_REQUIRED = 'approval_required';
    public const DELEGATED_SAFE = 'delegated_safe';
    public const FORBIDDEN = 'forbidden';

    /** @return array<string,mixed> */
    public function inspect(string $domain, string $action, bool $founderApproved = false, bool $delegationGranted = false): array
    {
        $domain = trim($domain);
        $action = trim($action);
        if ($domain === '' || $action === '') {
            throw new InvalidArgumentException('Founder capability domain and action are required.');
        }

        $configured = data_get(config('najm-hoda-founder-capabilities.domains', []), $domain . '.' . $action);
        $level = is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('najm-hoda-founder-capabilities.default_level', self::FORBIDDEN);

        if (! in_array($level, [self::OBSERVE, self::PROPOSE, self::APPROVAL_REQUIRED, self::DELEGATED_SAFE, self::FORBIDDEN], true)) {
            $level = self::FORBIDDEN;
        }

        $allowed = match ($level) {
            self::OBSERVE, self::PROPOSE => true,
            self::APPROVAL_REQUIRED => $founderApproved,
            self::DELEGATED_SAFE => $delegationGranted,
            default => false,
        };

        return [
            'domain' => $domain,
            'action' => $action,
            'level' => $level,
            'allowed' => $allowed,
            'requires_founder_approval' => $level === self::APPROVAL_REQUIRED,
            'requires_delegation' => $level === self::DELEGATED_SAFE,
            'forbidden' => $level === self::FORBIDDEN,
            'reason' => $this->reason($level, $allowed),
        ];
    }

    public function canExecute(string $domain, string $action, bool $founderApproved = false, bool $delegationGranted = false): bool
    {
        $decision = $this->inspect($domain, $action, $founderApproved, $delegationGranted);

        // Observation and proposal are not domain mutations and therefore are not
        // considered executable mutations by this method.
        if (in_array($decision['level'], [self::OBSERVE, self::PROPOSE], true)) {
            return false;
        }

        return (bool) $decision['allowed'];
    }

    /** @return array<string,array<string,string>> */
    public function matrix(): array
    {
        $domains = config('najm-hoda-founder-capabilities.domains', []);
        return is_array($domains) ? $domains : [];
    }

    protected function reason(string $level, bool $allowed): string
    {
        if ($level === self::FORBIDDEN) return 'action_forbidden_by_policy';
        if ($level === self::APPROVAL_REQUIRED && ! $allowed) return 'explicit_founder_approval_required';
        if ($level === self::DELEGATED_SAFE && ! $allowed) return 'explicit_delegation_grant_required';
        if ($level === self::PROPOSE) return 'proposal_only_no_mutation';
        if ($level === self::OBSERVE) return 'read_only';
        return 'authorized_by_policy';
    }
}
