<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NajmHodaActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'conversation_id' => $this->conversation_id ? (int) $this->conversation_id : null,
            'action' => (string) $this->action,
            'contract_version' => (int) $this->contract_version,
            'risk' => (string) $this->risk,
            'mode' => (string) $this->mode,
            'input' => (array) $this->input,
            'expected_output' => (array) ($this->expected_output ?? []),
            'consent_required' => (bool) $this->consent_required,
            'status' => (string) $this->status,
            'consent_evidence_id' => $this->consent_evidence_id,
            'consented_at' => $this->consented_at?->utc()->toIso8601String(),
            'evidence_id' => $this->evidence_id,
            'runtime_run_id' => $this->runtime_run_id,
            'applied_at' => $this->applied_at?->utc()->toIso8601String(),
            'error_code' => $this->error_code,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
