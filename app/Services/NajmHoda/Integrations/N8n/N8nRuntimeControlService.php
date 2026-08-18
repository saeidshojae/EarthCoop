<?php

namespace App\Services\NajmHoda\Integrations\N8n;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\DB;

class N8nRuntimeControlService
{
    public function __construct(protected RuntimeEventBus $events)
    {
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        $row = DB::table('najm_hoda_n8n_runtime_controls')->orderByDesc('id')->first();
        $disabled = [];
        if ($row && is_string($row->disabled_workflows ?? null) && trim($row->disabled_workflows) !== '') {
            $decoded = json_decode($row->disabled_workflows, true);
            $disabled = is_array($decoded) ? array_values(array_unique(array_map('strval', $decoded))) : [];
        }

        return [
            'outbound_enabled' => $row ? (bool) $row->outbound_enabled : true,
            'callback_ingress_enabled' => $row ? (bool) $row->callback_ingress_enabled : true,
            'disabled_workflows' => $disabled,
            'secret_rotation_verified_at' => $row?->secret_rotation_verified_at,
            'secret_rotation_verified_by' => $row?->secret_rotation_verified_by,
            'reason' => $row?->reason,
            'updated_by' => $row?->updated_by,
            'updated_at' => $row?->updated_at,
        ];
    }

    public function outboundEnabled(): bool
    {
        return (bool) $this->state()['outbound_enabled'];
    }

    public function callbackIngressEnabled(): bool
    {
        return (bool) $this->state()['callback_ingress_enabled'];
    }

    public function workflowEnabled(string $workflow): bool
    {
        return !in_array($workflow, (array) $this->state()['disabled_workflows'], true);
    }

    /**
     * @param array<int, string> $disabledWorkflows
     * @return array<string, mixed>
     */
    public function update(
        bool $outboundEnabled,
        bool $callbackIngressEnabled,
        ?int $actorId,
        ?string $reason = null,
        array $disabledWorkflows = [],
    ): array {
        $reason = trim((string) $reason);
        $reason = $reason !== '' ? mb_substr($reason, 0, 500) : null;
        $allowed = config('najm-hoda-n8n.allowed_workflows', []);
        $allowedNames = is_array($allowed) ? array_keys($allowed) : [];
        $disabledWorkflows = array_values(array_intersect(
            $allowedNames,
            array_values(array_unique(array_map('strval', $disabledWorkflows)))
        ));

        $previous = $this->state();

        DB::table('najm_hoda_n8n_runtime_controls')->insert([
            'outbound_enabled' => $outboundEnabled,
            'callback_ingress_enabled' => $callbackIngressEnabled,
            'disabled_workflows' => json_encode($disabledWorkflows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'secret_rotation_verified_at' => $previous['secret_rotation_verified_at'] ?? null,
            'secret_rotation_verified_by' => $previous['secret_rotation_verified_by'] ?? null,
            'reason' => $reason,
            'updated_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $state = $this->state();

        $this->events->emit('najm_hoda.integration.n8n.runtime_controls_updated', [
            'actor_id' => $actorId ? 'user:' . $actorId : 'system',
            'scope' => 'integration:n8n',
            'risk' => 'medium',
            'outbound_enabled' => $outboundEnabled,
            'callback_ingress_enabled' => $callbackIngressEnabled,
            'disabled_workflow_count' => count($disabledWorkflows),
            'reason_present' => $reason !== null,
        ]);

        return $state;
    }

    /** @return array<string, mixed> */
    public function markSecretRotationVerified(?int $actorId, ?string $reason = null): array
    {
        $current = $this->state();
        $reason = trim((string) $reason);
        $reason = $reason !== '' ? mb_substr($reason, 0, 500) : 'secret_rotation_verified';

        DB::table('najm_hoda_n8n_runtime_controls')->insert([
            'outbound_enabled' => (bool) ($current['outbound_enabled'] ?? true),
            'callback_ingress_enabled' => (bool) ($current['callback_ingress_enabled'] ?? true),
            'disabled_workflows' => json_encode((array) ($current['disabled_workflows'] ?? []), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'secret_rotation_verified_at' => now(),
            'secret_rotation_verified_by' => $actorId,
            'reason' => $reason,
            'updated_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $state = $this->state();

        $this->events->emit('najm_hoda.integration.n8n.secret_rotation_verified', [
            'actor_id' => $actorId ? 'user:' . $actorId : 'system',
            'scope' => 'integration:n8n',
            'risk' => 'medium',
            'verified_at' => $state['secret_rotation_verified_at'] ?? null,
        ]);

        return $state;
    }
}
