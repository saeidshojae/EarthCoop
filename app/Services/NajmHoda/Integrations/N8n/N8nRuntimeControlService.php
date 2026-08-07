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

        return [
            'outbound_enabled' => $row ? (bool) $row->outbound_enabled : true,
            'callback_ingress_enabled' => $row ? (bool) $row->callback_ingress_enabled : true,
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

    /** @return array<string, mixed> */
    public function update(bool $outboundEnabled, bool $callbackIngressEnabled, ?int $actorId, ?string $reason = null): array
    {
        $reason = trim((string) $reason);
        $reason = $reason !== '' ? mb_substr($reason, 0, 500) : null;

        DB::table('najm_hoda_n8n_runtime_controls')->insert([
            'outbound_enabled' => $outboundEnabled,
            'callback_ingress_enabled' => $callbackIngressEnabled,
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
            'reason_present' => $reason !== null,
        ]);

        return $state;
    }
}
