<?php

namespace App\Modules\Secretariat\Services;

use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * S3 adapter boundary between source domains and the Registry.
 *
 * This service never mutates GroupSession, MeetingMinute, Proposal or Resolution
 * business state. It only creates idempotent Secretariat drafts containing an
 * archival snapshot plus stable provenance. Formal registration remains a
 * separate human-authorized S1 workflow.
 */
class SecretariatGovernanceIntegrationService
{
    public function __construct(
        private readonly SecretariatRecordService $records,
        private readonly SecretariatRelationService $relations,
    ) {
    }

    public function proposeApprovedMeetingMinute(
        NajmHodaGroupMeetingMinute $minute,
        User $actor,
    ): SecretariatRecord {
        return DB::transaction(function () use ($minute, $actor) {
            /** @var NajmHodaGroupMeetingMinute $locked */
            $locked = NajmHodaGroupMeetingMinute::query()
                ->with(['session', 'group'])
                ->whereKey($minute->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'approved' || $locked->approved_by === null || $locked->approved_at === null) {
                throw ValidationException::withMessages([
                    'meeting_minute' => 'Only an approved meeting minute can be proposed to the Secretariat.',
                ]);
            }

            if ($locked->session === null || (int) $locked->session->group_id !== (int) $locked->group_id) {
                throw new LogicException('Approved meeting minute has inconsistent GroupSession provenance.');
            }

            $office = $this->groupOffice((int) $locked->group_id);

            $existing = $this->existingSourceRecord($office, 'meeting_minute', (int) $locked->id);
            if ($existing !== null) {
                return $existing;
            }

            return $this->records->createDraft($office, $actor, [
                'record_type' => 'meeting_minute',
                'direction' => 'internal',
                'title' => $locked->session->title,
                'subject' => $locked->session->subject,
                'summary' => $locked->summary,
                'body' => $locked->minutes,
                'source_type' => 'meeting_minute',
                'source_id' => $locked->id,
                'metadata' => [
                    's3_snapshot' => [
                        'group_session_id' => (int) $locked->group_session_id,
                        'session_status' => (string) $locked->session->status,
                        'session_started_at' => $locked->session->started_at?->toIso8601String(),
                        'session_ended_at' => $locked->session->ended_at?->toIso8601String(),
                        'minute_approved_by' => (int) $locked->approved_by,
                        'minute_approved_at' => $locked->approved_at?->toIso8601String(),
                    ],
                ],
            ]);
        }, 5);
    }

    public function proposeAdoptedResolution(
        Resolution $resolution,
        User $actor,
        ?SecretariatRecord $meetingMinuteRecord = null,
    ): SecretariatRecord {
        return DB::transaction(function () use ($resolution, $actor, $meetingMinuteRecord) {
            /** @var Resolution $locked */
            $locked = Resolution::query()
                ->with(['proposal', 'group'])
                ->whereKey($resolution->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'adopted' || $locked->adopted_at === null) {
                throw ValidationException::withMessages([
                    'resolution' => 'Only an adopted Governance resolution can be proposed to the Secretariat.',
                ]);
            }

            if ($locked->proposal === null || (int) $locked->proposal->group_id !== (int) $locked->group_id) {
                throw new LogicException('Adopted resolution has inconsistent proposal provenance.');
            }

            $office = $this->groupOffice((int) $locked->group_id);
            $existing = $this->existingSourceRecord($office, 'governance_resolution', (int) $locked->id);

            if ($existing !== null) {
                $this->linkDecisionToMinuteIfRequested($existing, $meetingMinuteRecord, $actor);
                return $existing;
            }

            $record = $this->records->createDraft($office, $actor, [
                'record_type' => 'resolution',
                'direction' => 'internal',
                'title' => $locked->proposal->title,
                'subject' => $locked->proposal->summary,
                'summary' => $locked->proposal->summary,
                'body' => $locked->proposal->description,
                'source_type' => 'governance_resolution',
                'source_id' => $locked->id,
                'metadata' => [
                    // Deliberately excludes quorum/vote totals/effect_status. Those
                    // remain Governance business truth rather than Registry state.
                    's3_snapshot' => [
                        'proposal_id' => (int) $locked->proposal_id,
                        'resolution_type' => (string) $locked->type,
                        'resolution_status' => (string) $locked->status,
                        'adopted_by' => $locked->adopted_by !== null ? (int) $locked->adopted_by : null,
                        'adopted_at' => $locked->adopted_at?->toIso8601String(),
                        'effective_at' => $locked->effective_at?->toIso8601String(),
                    ],
                ],
            ]);

            $this->linkDecisionToMinuteIfRequested($record, $meetingMinuteRecord, $actor);

            return $record;
        }, 5);
    }

    private function groupOffice(int $groupId): SecretariatOffice
    {
        return SecretariatOffice::query()
            ->where('office_type', 'group')
            ->where('scope_type', 'group')
            ->where('scope_id', $groupId)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function existingSourceRecord(SecretariatOffice $office, string $sourceType, int $sourceId): ?SecretariatRecord
    {
        return SecretariatRecord::query()
            ->where('office_id', $office->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }

    private function linkDecisionToMinuteIfRequested(
        SecretariatRecord $resolutionRecord,
        ?SecretariatRecord $meetingMinuteRecord,
        User $actor,
    ): void {
        if ($meetingMinuteRecord === null) {
            return;
        }

        if ($meetingMinuteRecord->record_type !== 'meeting_minute' || $meetingMinuteRecord->source_type !== 'meeting_minute') {
            throw ValidationException::withMessages([
                'meeting_minute_record' => 'Decision relation requires a Secretariat meeting-minute record.',
            ]);
        }

        if ((int) $meetingMinuteRecord->office_id !== (int) $resolutionRecord->office_id) {
            throw ValidationException::withMessages([
                'meeting_minute_record' => 'Resolution and meeting minute must belong to the same Secretariat office.',
            ]);
        }

        $this->relations->add(
            $resolutionRecord,
            $meetingMinuteRecord,
            'decision_of',
            $actor,
            ['integration' => 's3_governance']
        );
    }
}
