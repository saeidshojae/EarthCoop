<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRelation;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only relation suggestions from explicit provenance only.
 *
 * This service deliberately does not use title/body similarity. A relation is
 * suggested only when an existing record carries a stable record id in its
 * immutable/provenance metadata and both records are actor-viewable.
 */
class SecretariatRelationSuggestionService
{
    /** @return array<int,array<string,mixed>> */
    public function suggestForRecord(SecretariatRecord $record, User $actor): array
    {
        $record = SecretariatRecord::query()->with('office')->findOrFail($record->id);
        Gate::forUser($actor)->authorize('view', $record);

        $candidates = [];
        $metadata = is_array($record->metadata) ? $record->metadata : [];
        $snapshot = is_array($metadata['s3_snapshot'] ?? null) ? $metadata['s3_snapshot'] : [];

        if ($record->record_type === 'execution_record' && $record->source_type === 'action_item') {
            $resolutionRecordId = (int) ($snapshot['resolution_record_id'] ?? 0);
            if ($resolutionRecordId > 0) {
                $candidates[] = [
                    'target_record_id' => $resolutionRecordId,
                    'relation_type' => 'report_of',
                    'basis' => 's3_snapshot.resolution_record_id',
                ];
            }
        }

        if (in_array($record->record_type, ['outgoing_letter', 'internal_correspondence'], true)) {
            $replyToRecordId = (int) ($metadata['reply_to_record_id'] ?? 0);
            if ($replyToRecordId > 0) {
                $candidates[] = [
                    'target_record_id' => $replyToRecordId,
                    'relation_type' => 'responds_to',
                    'basis' => 'record_metadata.reply_to_record_id',
                ];
            }
        }

        if ($record->record_type === 'resolution' && $record->source_type === 'governance_resolution') {
            $minuteRecordId = (int) ($metadata['meeting_minute_record_id'] ?? 0);
            if ($minuteRecordId > 0) {
                $candidates[] = [
                    'target_record_id' => $minuteRecordId,
                    'relation_type' => 'decision_of',
                    'basis' => 'record_metadata.meeting_minute_record_id',
                ];
            }
        }

        $suggestions = [];
        foreach ($candidates as $candidate) {
            $target = SecretariatRecord::query()->with('office')->find((int) $candidate['target_record_id']);
            if (! $target
                || $target->id === $record->id
                || (int) $target->office_id !== (int) $record->office_id
                || ! Gate::forUser($actor)->allows('view', $target)) {
                continue;
            }

            $exists = SecretariatRelation::query()
                ->where('source_record_id', $record->id)
                ->where('target_record_id', $target->id)
                ->where('relation_type', $candidate['relation_type'])
                ->exists();
            if ($exists) {
                continue;
            }

            $suggestions[] = [
                'source_record_id' => (int) $record->id,
                'target_record_id' => (int) $target->id,
                'target_registry_number' => $target->registry_number,
                'target_title' => $target->title,
                'relation_type' => $candidate['relation_type'],
                'basis' => $candidate['basis'],
                'confidence' => 'deterministic_provenance',
            ];
        }

        return $suggestions;
    }
}
