<?php

namespace App\Services\NajmHoda;

use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;

class NajmHodaInteractionBoundaryService
{
    public function __construct(
        protected NajmHodaCapabilityRegistry $capabilityRegistry
    ) {
    }

    /**
     * Classify an interaction without executing anything.
     *
     * The boundary is intentionally conservative: ordinary chat stays in
     * answer mode. An executable path is considered only when the caller
     * supplies an explicit capability/action request in structured context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function classify(string $message, array $context = []): array
    {
        $requestedAction = trim((string) ($context['requested_action'] ?? $context['capability_action'] ?? ''));

        if ($requestedAction === '') {
            return [
                'mode' => 'answer',
                'reason' => 'no_explicit_action_request',
                'message' => $message,
            ];
        }

        $contract = $this->capabilityRegistry->contract($requestedAction);
        if ($contract === null) {
            return [
                'mode' => 'blocked_action',
                'reason' => 'unknown_action_contract',
                'action' => $requestedAction,
            ];
        }

        if (!(bool) ($contract['enabled'] ?? true)) {
            return [
                'mode' => 'blocked_action',
                'reason' => 'action_disabled',
                'action' => $requestedAction,
            ];
        }

        return [
            'mode' => 'action',
            'reason' => 'explicit_registered_capability',
            'action' => $requestedAction,
            'risk' => (string) ($contract['risk'] ?? 'low'),
            'default_mode' => (string) ($contract['mode'] ?? 'propose'),
            'input' => is_array($context['action_input'] ?? null) ? $context['action_input'] : [],
        ];
    }
}
