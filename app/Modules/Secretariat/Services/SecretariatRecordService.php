<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class SecretariatRecordService
{
    private const DIRECTIONS = ['incoming', 'outgoing', 'internal', 'none'];
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];
    private const RECORD_TYPES = [
        'incoming_letter',
        'outgoing_letter',
        'internal_correspondence',
        'meeting_minute',
        'resolution',
        'formal_decision',
        'contract',
        'memorandum_of_understanding',
        'agreement',
        'policy',
        'directive',
        'official_report',
        'notice',
        'official_note',
        'financial_record',
        'execution_record',
        'election_record',
        'case_record',
        'other',
    ];
    private const DESCRIPTOR_SOURCES = ['manual', 'external_document'];

    public function __construct(
        private readonly SecretariatVersionService $versions,
        private readonly SecretariatAuditService $audit,
        private readonly SecretariatTransitionService $transitions,
        private readonly RegistryNumberService $numbers,
        private readonly SecretariatAttachmentService $attachments,
    ) {
    }

    public function createDraft(SecretariatOffice $office, User $actor, array $attributes): SecretariatRecord
    {
        return DB::transaction(function () use ($office, $actor, $attributes) {
            SecretariatMorphMap::register();

            $direction = (string) ($attributes['direction'] ?? 'none');
            $confidentiality = (string) ($attributes['confidentiality'] ?? $office->default_confidentiality);
            $recordType = (string) ($attributes['record_type'] ?? '');
            $sourceType = $attributes['source_type'] ?? null;
            $sourceId = $attributes['source_id'] ?? null;

            if (! in_array($direction, self::DIRECTIONS, true)) {
                throw ValidationException::withMessages(['direction' => 'Unsupported Secretariat direction.']);
            }
            if (! in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
                throw ValidationException::withMessages(['confidentiality' => 'Unsupported Secretariat confidentiality.']);
            }
            if (! in_array($recordType, self::RECORD_TYPES, true)) {
                throw ValidationException::withMessages(['record_type' => 'Unsupported Secretariat record type.']);
            }
            if (trim((string) ($attributes['title'] ?? '')) === '') {
                throw ValidationException::withMessages(['title' => 'A Secretariat record requires a title.']);
            }

            $this->validateSource($sourceType, $sourceId);

            $record = SecretariatRecord::query()->create([
                'office_id' => $office->id,
                'record_type' => $recordType,
                'direction' => $direction,
                'title' => $attributes['title'],
                'subject' => $attributes['subject'] ?? null,
                'summary' => $attributes['summary'] ?? null,
                'status' => 'draft',
                'confidentiality' => $confidentiality,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            $version = $this->versions->append($record, $actor, [
                'title' => $record->title,
                'subject' => $record->subject,
                'summary' => $record->summary,
                'body' => $attributes['body'] ?? null,
            ], 'Initial draft', false);

            $this->audit->append($office, $record, $actor, 'created', [
                'version_number' => $version->version_number,
                'source_type' => $record->source_type,
                'source_id' => $record->source_id,
            ]);

            return $record->refresh();
        });
    }

    public function editDraft(SecretariatRecord $record, User $actor, array $content, ?string $reason = null): SecretariatRecord
    {
        if ($record->status !== 'draft') {
            throw new LogicException('Only draft Secretariat records can be edited directly.');
        }

        $version = $this->versions->append($record, $actor, $content, $reason ?? 'Draft revision');
        $record = $record->refresh();

        $this->audit->append($record->office, $record, $actor, 'draft_updated', [
            'version_number' => $version->version_number,
            'change_reason' => $reason,
        ]);

        return $record;
    }

    public function submitForApproval(SecretariatRecord $record, User $actor): SecretariatRecord
    {
        return $this->transitions->transition($record, 'pending_approval', $actor);
    }

    public function returnToDraft(SecretariatRecord $record, User $actor, ?string $reason = null): SecretariatRecord
    {
        return $this->transitions->transition($record, 'draft', $actor, ['reason' => $reason]);
    }

    public function register(SecretariatRecord $record, User $actor): SecretariatRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            /** @var SecretariatRecord $locked */
            $locked = SecretariatRecord::query()
                ->with(['office', 'currentVersion'])
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->registry_number !== null) {
                return $locked;
            }

            $this->transitions->assertAllowed($locked->status, 'registered');

            if ($locked->currentVersion === null) {
                throw new LogicException('A Secretariat record cannot be registered without a current version.');
            }

            $allocation = $this->numbers->allocate($locked->office, $locked->record_type);
            $officialVersion = $this->versions->markOfficial($locked->currentVersion, $actor);
            $now = now();

            $locked->performControlledMutation(function (SecretariatRecord $target) use ($allocation, $officialVersion, $actor, $now): void {
                $target->forceFill([
                    'status' => 'registered',
                    'registry_number' => $allocation['number'],
                    'registry_sequence' => $allocation['sequence'],
                    'registry_year' => $allocation['year'],
                    'registry_family' => $allocation['family'],
                    'registered_by' => $actor->id,
                    'registered_at' => $now,
                    'approved_by' => $actor->id,
                    'approved_at' => $now,
                    'current_version_id' => $officialVersion->id,
                ])->save();
            });

            $this->audit->append($locked->office, $locked, $actor, 'approved', [
                'version_number' => $officialVersion->version_number,
            ]);
            $this->audit->append($locked->office, $locked, $actor, 'registered', [
                'registry_number' => $allocation['number'],
                'registry_sequence' => $allocation['sequence'],
                'registry_year' => $allocation['year'],
                'registry_family' => $allocation['family'],
                'version_number' => $officialVersion->version_number,
            ]);

            return $locked->refresh();
        });
    }

    public function createAmendment(SecretariatRecord $record, User $actor, array $content, string $reason): SecretariatRecordVersion
    {
        if (! in_array($record->status, ['registered', 'active', 'closed'], true)) {
            throw new LogicException('Amendments require a registered Secretariat record.');
        }

        return $this->versions->append($record, $actor, $content, $reason, true, false);
    }

    public function approveAmendment(SecretariatRecordVersion $version, User $actor): SecretariatRecord
    {
        $record = $version->record;
        if (! in_array($record->status, ['registered', 'active', 'closed'], true)) {
            throw new LogicException('Only a formal Secretariat record can receive an approved amendment.');
        }
        if ($version->is_official) {
            return $record->refresh();
        }

        $approved = $this->versions->markOfficial($version, $actor, true);
        $record = $record->refresh();

        $this->audit->append($record->office, $record, $actor, 'approved', [
            'version_number' => $approved->version_number,
            'amendment' => true,
        ]);

        return $record;
    }

    public function transition(SecretariatRecord $record, string $to, User $actor, array $metadata = []): SecretariatRecord
    {
        return $this->transitions->transition($record, $to, $actor, $metadata);
    }

    public function deleteDraft(SecretariatRecord $record): void
    {
        DB::transaction(function () use ($record): void {
            /** @var SecretariatRecord $locked */
            $locked = SecretariatRecord::query()
                ->with(['attachments', 'aclEntries'])
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['draft', 'cancelled'], true)) {
                throw new LogicException('Formal Secretariat records cannot be hard-deleted.');
            }

            if ($locked->aclEntries->isNotEmpty()) {
                throw new LogicException('A Secretariat draft with ACL history cannot be hard-deleted; cancel it to preserve access history.');
            }

            foreach ($locked->attachments as $attachment) {
                $this->attachments->deleteDraftAttachment($attachment);
            }

            $locked->delete();
        });
    }

    private function validateSource(mixed $sourceType, mixed $sourceId): void
    {
        if ($sourceType === null) {
            if ($sourceId !== null) {
                throw ValidationException::withMessages(['source_id' => 'A source id cannot exist without a source type.']);
            }
            return;
        }

        $sourceType = (string) $sourceType;
        if (in_array($sourceType, self::DESCRIPTOR_SOURCES, true)) {
            if ($sourceId !== null) {
                throw ValidationException::withMessages(['source_id' => 'Descriptor sources do not use polymorphic ids.']);
            }
            return;
        }

        $class = Relation::getMorphedModel($sourceType);
        if ($class === null) {
            throw ValidationException::withMessages(['source_type' => 'Unknown or unmapped Secretariat source token.']);
        }
        if ($sourceId === null || ! $class::query()->whereKey($sourceId)->exists()) {
            throw ValidationException::withMessages(['source_id' => 'Secretariat source does not exist.']);
        }
    }
}
