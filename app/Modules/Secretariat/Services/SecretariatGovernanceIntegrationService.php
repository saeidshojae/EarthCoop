<?php

namespace App\Modules\Secretariat\Services;

use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRelation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * S3 adapter boundary between source domains and the Registry.
 *
 * This service never mutates GroupSession, MeetingMinute, Governance Resolution
 * or Action Item business state. It only creates idempotent Secretariat drafts
 * containing an archival snapshot plus stable provenance. Formal registration
 * remains a separate human-authorized S1 workflow.
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

    public function proposeCompletedActionExecutionReport(
        NajmHodaGroupActionItem $action,
        SecretariatRecord $officialResolutionRecord,
        User $actor,
    ): SecretariatRecord {
        return DB::transaction(function () use ($action, $officialResolutionRecord, $actor) {
            /** @var NajmHodaGroupActionItem $locked */
            $locked = NajmHodaGroupActionItem::query()
                ->whereKey($action->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'done') {
                throw ValidationException::withMessages([
                    'action_item' => 'Only a done Action Item can produce a Secretariat execution report draft.',
                ]);
            }

            $resolutionRecord = SecretariatRecord::query()
                ->whereKey($officialResolutionRecord->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $resolutionRecord->record_type !== 'resolution'
                || $resolutionRecord->source_type !== 'governance_resolution'
                || $resolutionRecord->registry_number === null
                || ! in_array($resolutionRecord->status, ['registered', 'active', 'closed', 'archived'], true)
            ) {
                throw ValidationException::withMessages([
                    'resolution_record' => 'Action execution reporting requires a formally registered Governance resolution record.',
                ]);
            }

            /** @var Resolution|null $sourceResolution */
            $sourceResolution = Resolution::query()->find($resolutionRecord->source_id);
            if ($sourceResolution === null || (int) $sourceResolution->group_id !== (int) $locked->group_id) {
                throw ValidationException::withMessages([
                    'resolution_record' => 'Action Item and Governance resolution must belong to the same group.',
                ]);
            }

            $office = $this->groupOffice((int) $locked->group_id);
            if ((int) $resolutionRecord->office_id !== (int) $office->id) {
                throw ValidationException::withMessages([
                    'resolution_record' => 'Execution report and resolution must belong to the same Secretariat office.',
                ]);
            }

            $minuteRecord = $this->assertActionResolutionProvenance($locked, $resolutionRecord, $office);

            $existing = $this->existingSourceRecord($office, 'action_item', (int) $locked->id);
            if ($existing !== null) {
                $this->relations->add(
                    $existing,
                    $resolutionRecord,
                    'report_of',
                    $actor,
                    ['integration' => 's3_action_execution', 'action_item_id' => (int) $locked->id]
                );
                return $existing;
            }

            $record = $this->records->createDraft($office, $actor, [
                'record_type' => 'execution_record',
                'direction' => 'internal',
                'title' => 'گزارش اجرای: ' . $locked->title,
                'subject' => $locked->title,
                'summary' => $locked->details,
                'body' => $locked->details,
                'source_type' => 'action_item',
                'source_id' => $locked->id,
                'metadata' => [
                    // The Action Item remains the live owner of status, assignee,
                    // priority and due date. Registry stores only archival provenance.
                    's3_snapshot' => [
                        'action_item_id' => (int) $locked->id,
                        'action_status' => (string) $locked->status,
                        'meeting_minute_id' => (int) $minuteRecord->source_id,
                        'meeting_minute_record_id' => (int) $minuteRecord->id,
                        'resolution_record_id' => (int) $resolutionRecord->id,
                        'governance_resolution_id' => (int) $resolutionRecord->source_id,
                    ],
                ],
            ]);

            $this->relations->add(
                $record,
                $resolutionRecord,
                'report_of',
                $actor,
                ['integration' => 's3_action_execution', 'action_item_id' => (int) $locked->id]
            );

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

    private function assertActionResolutionProvenance(
        NajmHodaGroupActionItem $action,
        SecretariatRecord $resolutionRecord,
        SecretariatOffice $office,
    ): SecretariatRecord {
        $meta = is_array($action->meta) ? $action->meta : [];
        $meetingMinuteId = (int) ($meta['meeting_minute_id'] ?? 0);

        if ($meetingMinuteId <= 0) {
            throw ValidationException::withMessages([
                'action_item' => 'Execution reporting requires Action Item provenance to an approved meeting minute.',
            ]);
        }

        /** @var NajmHodaGroupMeetingMinute|null $sourceMinute */
        $sourceMinute = NajmHodaGroupMeetingMinute::query()->find($meetingMinuteId);
        if (
            $sourceMinute === null
            || $sourceMinute->status !== 'approved'
            || (int) $sourceMinute->group_id !== (int) $action->group_id
        ) {
            throw ValidationException::withMessages([
                'action_item' => 'Action Item meeting-minute provenance is missing, unapproved, or belongs to another group.',
            ]);
        }

        /** @var SecretariatRecord|null $minuteRecord */
        $minuteRecord = SecretariatRecord::query()
            ->where('office_id', $office->id)
            ->where('record_type', 'meeting_minute')
            ->where('source_type', 'meeting_minute')
            ->where('source_id', $meetingMinuteId)
            ->whereNotNull('registry_number')
            ->whereIn('status', ['registered', 'active', 'closed', 'archived'])
            ->first();

        if ($minuteRecord === null) {
            throw ValidationException::withMessages([
                'action_item' => 'Execution reporting requires the Action Item meeting minute to be formally registered.',
            ]);
        }

        $decisionRelationExists = SecretariatRelation::query()
            ->where('source_record_id', $resolutionRecord->id)
            ->where('target_record_id', $minuteRecord->id)
            ->where('relation_type', 'decision_of')
            ->exists();

        if (! $decisionRelationExists) {
            throw ValidationException::withMessages([
                'resolution_record' => 'The Governance resolution is not formally linked to the Action Item meeting minute.',
            ]);
        }

        return $minuteRecord;
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
