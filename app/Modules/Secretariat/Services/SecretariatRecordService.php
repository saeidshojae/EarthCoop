<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class SecretariatRecordService
{
    private const DIRECTIONS = ['incoming', 'outgoing', 'internal', 'none'];
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];

    public function __construct(
        private readonly SecretariatVersionService $versions,
        private readonly SecretariatAuditService $audit,
        private readonly SecretariatTransitionService $transitions,
        private readonly RegistryNumberService $numbers,
    ) {
    }

    public function createDraft(SecretariatOffice $office, User $actor, array $attributes): SecretariatRecord
    {
        return DB::transaction(function () use ($office, $actor, $attributes) {
            $direction = (string) ($attributes['direction'] ?? 'none');
            $confidentiality = (string) ($attributes['confidentiality'] ?? $office->default_confidentiality);

            if (! in_array($direction, self::DIRECTIONS, true)) {
                throw ValidationException::withMessages(['direction' => 'Unsupported Secretariat direction.']);
            }
            if (! in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
                throw ValidationException::withMessages(['confidentiality' => 'Unsupported Secretariat confidentiality.']);
            }
            if (trim((string) ($attributes['title'] ?? '')) === '') {
                throw ValidationException::withMessages(['title' => 'A Secretariat record requires a title.']);
            }
            if (trim((string) ($attributes['record_type'] ?? '')) === '') {
                throw ValidationException::withMessages(['record_type' => 'A Secretariat record requires a type.']);
            }

            $record = SecretariatRecord::query()->create([
                'office_id' => $office->id,
                'record_type' => $attributes['record_type'],
                'direction' => $direction,
                'title' => $attributes['title'],
                'subject' => $attributes['subject'] ?? null,
                'summary' => $attributes['summary'] ?? null,
                'status' => 'draft',
                'confidentiality' => $confidentiality,
                'source_type' => $attributes['source_type'] ?? null,
                'source_id' => $attributes['source_id'] ?? null,
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

        $this->versions->append($record, $actor, $content, $reason ?? 'Draft revision');

        return $record->refresh();
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

            if ($locked->registry_number !== null && $locked->status === 'registered') {
                return $locked;
            }

            $this->transitions->assertAllowed($locked->status, 'registered');

            if ($locked->currentVersion === null) {
                throw new LogicException('A Secretariat record cannot be registered without a current version.');
            }

            $allocation = $this->numbers->allocate($locked->office, $locked->record_type);
            $officialVersion = $this->versions->markOfficial($locked->currentVersion, $actor);
            $now = now();

            $locked->forceFill([
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

    public function createAmendment(SecretariatRecord $record, User $actor, array $content, string $reason): SecretariatRecord
    {
        if (! in_array($record->status, ['registered', 'active', 'closed'], true)) {
            throw new LogicException('Amendments require a registered Secretariat record.');
        }

        $this->versions->append($record, $actor, $content, $reason);

        return $record->refresh();
    }

    public function transition(SecretariatRecord $record, string $to, User $actor, array $metadata = []): SecretariatRecord
    {
        return $this->transitions->transition($record, $to, $actor, $metadata);
    }

    public function deleteDraft(SecretariatRecord $record): void
    {
        if (! in_array($record->status, ['draft', 'cancelled'], true)) {
            throw new LogicException('Formal Secretariat records cannot be hard-deleted.');
        }

        $record->delete();
    }
}
