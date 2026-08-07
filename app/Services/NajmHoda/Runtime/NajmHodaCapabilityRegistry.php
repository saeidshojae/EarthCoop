<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaCapabilityRegistry
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contract(string $action): ?array
    {
        $action = trim($action);
        if ($action === '') {
            return null;
        }

        $contract = config("najm-hoda.runtime.autonomy.capabilities.{$action}");
        if (!is_array($contract) || empty($contract)) {
            return null;
        }

        return array_merge([
            'enabled' => true,
            'version' => 1,
            'risk' => 'low',
            'mode' => 'propose',
            'required_input' => [],
            'optional_input' => [],
            'output' => [],
        ], $contract);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validateInput(string $action, array $input): array
    {
        $contract = $this->contract($action);
        if ($contract === null) {
            return ['valid' => false, 'reason' => 'unknown_action_contract'];
        }

        if (!(bool) ($contract['enabled'] ?? true)) {
            return ['valid' => false, 'reason' => 'action_disabled'];
        }

        $required = (array) ($contract['required_input'] ?? []);
        foreach ($required as $field) {
            $field = trim((string) $field);
            if ($field === '') {
                continue;
            }
            if (!array_key_exists($field, $input)) {
                return ['valid' => false, 'reason' => 'missing_required_input', 'field' => $field];
            }
        }

        return ['valid' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $goals
     * @return array<string, mixed>|null
     */
    public function makePlannedAction(
        string $action,
        array $input,
        string $priority,
        string $reason,
        bool $applyRequested,
        array $goals = []
    ): ?array {
        $contract = $this->contract($action);
        if ($contract === null) {
            $this->eventBus->emit('najm_hoda.autonomy.contract.rejected', [
                'action' => $action,
                'reason' => 'unknown_action_contract',
            ]);
            return null;
        }

        $validation = $this->validateInput($action, $input);
        if (!(bool) ($validation['valid'] ?? false)) {
            $this->eventBus->emit('najm_hoda.autonomy.contract.rejected', [
                'action' => $action,
                'reason' => (string) ($validation['reason'] ?? 'validation_failed'),
                'field' => $validation['field'] ?? null,
            ]);
            return null;
        }

        $allowApplyLowRisk = (bool) config('najm-hoda.runtime.autonomy.allow_apply_low_risk', false);
        $permissioningEnabled = (bool) config('najm-hoda.runtime.autonomy.permissioning_v2.enabled', true);
        $delegationEnforced = (bool) config(
            'najm-hoda.runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation',
            true
        );
        $risk = (string) ($contract['risk'] ?? 'low');
        $defaultMode = (string) ($contract['mode'] ?? 'propose');

        // Fail closed: an apply request can only become apply when permissioning
        // is enabled and delegation enforcement is explicitly active.
        $canApply = $applyRequested
            && $allowApplyLowRisk
            && $risk === 'low'
            && $permissioningEnabled
            && $delegationEnforced;

        $mode = $canApply ? 'apply' : 'propose';

        $planned = [
            'priority' => $priority,
            'action' => $action,
            'capability' => (string) ($contract['name'] ?? $action),
            'contract_version' => (int) ($contract['version'] ?? 1),
            'risk' => $risk,
            'mode' => $mode,
            'reason' => $reason,
            'goals' => $goals,
            'input' => $input,
            'expected_output' => (array) ($contract['output'] ?? []),
        ];

        $this->eventBus->emit('najm_hoda.autonomy.contract.accepted', [
            'action' => $action,
            'contract_version' => (int) ($contract['version'] ?? 1),
            'risk' => $risk,
            'mode' => $mode,
            'apply_requested' => $applyRequested,
            'delegation_enforced' => $delegationEnforced,
        ]);

        return $planned;
    }
}
