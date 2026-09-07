<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;

class FounderSecretariatCorrespondenceDraftService
{
    public function __construct(
        protected SecretariatCorrespondenceService $correspondence
    ) {}

    /** @return array<string,mixed> */
    public function draft(array $context): array
    {
        $officeId = (int) ($context['office_id'] ?? 0);
        $actorId = (int) ($context['requested_by'] ?? 0);
        $direction = (string) ($context['direction'] ?? '');
        $attributes = is_array($context['attributes'] ?? null) ? $context['attributes'] : [];
        $parties = is_array($context['parties'] ?? null) ? $context['parties'] : [];

        $office = $officeId > 0 ? SecretariatOffice::query()->find($officeId) : null;
        $actor = $actorId > 0 ? User::query()->find($actorId) : null;

        if (! $office || ! $actor) {
            return ['success' => false, 'status' => 'invalid_context', 'reason' => 'secretariat_office_and_actor_required'];
        }
        if (! in_array($direction, ['incoming', 'outgoing', 'internal'], true)) {
            return ['success' => false, 'status' => 'invalid_context', 'reason' => 'secretariat_direction_required'];
        }

        $record = $this->correspondence->createDraft($office, $actor, $direction, $attributes, $parties);

        return [
            'success' => true,
            'status' => 'draft_ready',
            'record_id' => (int) $record->id,
            'office_id' => (int) $record->office_id,
            'record_status' => (string) $record->status,
            'direction' => (string) $record->direction,
        ];
    }
}
