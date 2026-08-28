<?php

namespace App\Modules\Secretariat\Services;

use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\Gate;

/**
 * Deterministic read-side detector for source-domain facts that are eligible
 * for Secretariat capture but have not yet become formal Registry records.
 */
class SecretariatRegistrationIntelligenceService
{
    /** @return array<string,array<int,array<string,mixed>>> */
    public function inspectOffice(SecretariatOffice $office, User $actor, int $limit = 50): array
    {
        Gate::forUser($actor)->authorize('inspect', $office);
        if ($office->scope_type !== 'group' || $office->scope_id === null) {
            return ['unrecorded' => [], 'pending_registry' => []];
        }

        $limit = max(1, min(100, $limit));
        $unrecorded = [];
        $pending = [];
        $groupId = (int) $office->scope_id;

        foreach (NajmHodaGroupMeetingMinute::query()
            ->with('session')
            ->where('group_id', $groupId)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->orderBy('approved_at')
            ->limit($limit * 2)
            ->get() as $minute) {
            $this->classifySource(
                $office,
                'meeting_minute',
                (int) $minute->id,
                [
                    'source_kind' => 'approved_meeting_minute',
                    'source_id' => (int) $minute->id,
                    'source_status' => (string) $minute->status,
                    'title' => (string) ($minute->session?->title ?? ('Meeting Minute #' . $minute->id)),
                    'suggested_record_type' => 'meeting_minute',
                    'suggested_direction' => 'internal',
                    'suggested_office_id' => (int) $office->id,
                    'suggested_confidentiality' => (string) $office->default_confidentiality,
                    'suggestion_basis' => 'source-domain mapping + office default; no LLM classification',
                ],
                $unrecorded,
                $pending,
                $limit,
            );
        }

        foreach (Resolution::query()
            ->with('proposal')
            ->where('group_id', $groupId)
            ->where('status', 'adopted')
            ->whereNotNull('adopted_at')
            ->orderBy('adopted_at')
            ->limit($limit * 2)
            ->get() as $resolution) {
            $this->classifySource(
                $office,
                'governance_resolution',
                (int) $resolution->id,
                [
                    'source_kind' => 'adopted_governance_resolution',
                    'source_id' => (int) $resolution->id,
                    'source_status' => (string) $resolution->status,
                    'title' => (string) ($resolution->proposal?->title ?? ('Resolution #' . $resolution->id)),
                    'suggested_record_type' => 'resolution',
                    'suggested_direction' => 'internal',
                    'suggested_office_id' => (int) $office->id,
                    'suggested_confidentiality' => (string) $office->default_confidentiality,
                    'suggestion_basis' => 'source-domain mapping + office default; no LLM classification',
                ],
                $unrecorded,
                $pending,
                $limit,
            );
        }

        return [
            'unrecorded' => array_slice($unrecorded, 0, $limit),
            'pending_registry' => array_slice($pending, 0, $limit),
        ];
    }

    /** @param array<int,array<string,mixed>> $unrecorded @param array<int,array<string,mixed>> $pending */
    private function classifySource(
        SecretariatOffice $office,
        string $sourceType,
        int $sourceId,
        array $packet,
        array &$unrecorded,
        array &$pending,
        int $limit,
    ): void {
        if (count($unrecorded) >= $limit && count($pending) >= $limit) {
            return;
        }

        $record = SecretariatRecord::query()
            ->where('office_id', $office->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($record === null) {
            if (count($unrecorded) < $limit) {
                $unrecorded[] = $packet;
            }
            return;
        }

        if ($record->registry_number === null) {
            if (count($pending) < $limit) {
                $pending[] = array_merge($packet, [
                    'record_id' => (int) $record->id,
                    'registry_status' => (string) $record->status,
                    'suggested_record_type' => (string) $record->record_type,
                    'suggested_confidentiality' => (string) $record->confidentiality,
                ]);
            }
        }
    }
}
