<?php

namespace App\Services\NajmHoda\Api;

use App\Models\User;

class NajmHodaEvidenceService
{
    public function __construct(
        private readonly NajmHodaActionApplicationService $actions,
    ) {
    }

    public function get(User $actor, string $publicId): array
    {
        $record = $this->actions->getOwned($actor, $publicId);

        return [
            'action_id' => (string) $record->public_id,
            'evidence_id' => $record->evidence_id,
            'status' => (string) $record->status,
            'action' => (string) $record->action,
            'contract_version' => (int) $record->contract_version,
            'input_hash' => (string) $record->input_hash,
            'consent_evidence_id' => $record->consent_evidence_id,
            'runtime_run_id' => $record->runtime_run_id,
            'result' => $record->result,
            'error_code' => $record->error_code,
            'created_at' => optional($record->created_at)->utc()->toIso8601String(),
            'updated_at' => optional($record->updated_at)->utc()->toIso8601String(),
        ];
    }
}
