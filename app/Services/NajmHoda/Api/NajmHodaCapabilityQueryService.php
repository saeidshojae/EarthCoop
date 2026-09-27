<?php

namespace App\Services\NajmHoda\Api;

use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;

class NajmHodaCapabilityQueryService
{
    public function __construct(
        private readonly NajmHodaCapabilityRegistry $registry,
    ) {
    }

    public function all(): array
    {
        $capabilities = (array) config('najm-hoda.runtime.autonomy.capabilities', []);

        return collect(array_keys($capabilities))
            ->map(fn (string $action) => $this->get($action))
            ->filter()
            ->values()
            ->all();
    }

    public function get(string $action): ?array
    {
        $contract = $this->registry->contract($action);
        if ($contract === null) {
            return null;
        }

        return [
            'action' => $action,
            'version' => (int) ($contract['version'] ?? 1),
            'enabled' => (bool) ($contract['enabled'] ?? true),
            'risk' => (string) ($contract['risk'] ?? 'low'),
            'default_mode' => (string) ($contract['mode'] ?? 'propose'),
            'human_approval_required' => (bool) ($contract['human_approval_required'] ?? false),
            'required_input' => array_values((array) ($contract['required_input'] ?? [])),
            'optional_input' => array_values((array) ($contract['optional_input'] ?? [])),
            'output' => (array) ($contract['output'] ?? []),
        ];
    }
}
