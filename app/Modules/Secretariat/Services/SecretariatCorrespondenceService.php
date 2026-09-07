<?php

namespace App\Modules\Secretariat\Services;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCorrespondenceDetail;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecretariatCorrespondenceService
{
    private const TYPE_BY_DIRECTION = [
        'incoming' => 'incoming_letter',
        'outgoing' => 'outgoing_letter',
        'internal' => 'internal_correspondence',
    ];

    private const PARTY_ROLES = ['sender', 'recipient', 'cc', 'bcc', 'other'];
    private const PARTY_TYPES = ['user', 'group', 'external'];
    private const CHANNELS = ['internal', 'email', 'physical', 'api', 'other'];

    public function __construct(
        private readonly SecretariatRecordService $records,
        private readonly SecretariatRelationService $relations,
        private readonly SecretariatAuditService $audit,
    ) {
    }

    /**
     * @param array<string,mixed> $attributes
     * @param array<int,array<string,mixed>> $parties
     */
    public function createDraft(
        SecretariatOffice $office,
        User $actor,
        string $direction,
        array $attributes,
        array $parties,
    ): SecretariatRecord {
        if (! array_key_exists($direction, self::TYPE_BY_DIRECTION)) {
            throw ValidationException::withMessages(['direction' => 'Correspondence direction must be incoming, outgoing, or internal.']);
        }

        return DB::transaction(function () use ($office, $actor, $direction, $attributes, $parties) {
            $record = $this->records->createDraft($office, $actor, [
                'record_type' => self::TYPE_BY_DIRECTION[$direction],
                'direction' => $direction,
                'title' => $attributes['title'] ?? '',
                'subject' => $attributes['subject'] ?? null,
                'summary' => $attributes['summary'] ?? null,
                'body' => $attributes['body'] ?? null,
                'confidentiality' => $attributes['confidentiality'] ?? $office->default_confidentiality,
                'source_type' => $attributes['source_type'] ?? 'manual',
                'source_id' => $attributes['source_id'] ?? null,
                'metadata' => $attributes['record_metadata'] ?? null,
            ]);

            $this->putDetails($record, $actor, $direction, [
                'external_reference_number' => $attributes['external_reference_number'] ?? null,
                'received_at' => $attributes['received_at'] ?? null,
                'sent_at' => $attributes['sent_at'] ?? null,
                'channel' => $attributes['channel'] ?? null,
                'metadata' => $attributes['correspondence_metadata'] ?? null,
            ]);

            foreach ($parties as $party) {
                $this->addParty($record, $actor, $party);
            }

            $this->assertMinimumParties($record, $direction);

            return $record->load(['currentVersion', 'correspondenceDetail', 'parties']);
        });
    }

    /** @param array<string,mixed> $attributes */
    public function addParty(SecretariatRecord $record, User $actor, array $attributes): SecretariatParty
    {
        $this->assertCorrespondenceDraft($record);

        $role = (string) ($attributes['role'] ?? '');
        $partyType = (string) ($attributes['party_type'] ?? '');
        if (! in_array($role, self::PARTY_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Unsupported correspondence party role.']);
        }
        if (! in_array($partyType, self::PARTY_TYPES, true)) {
            throw ValidationException::withMessages(['party_type' => 'Unsupported correspondence party type.']);
        }

        $userId = isset($attributes['user_id']) ? (int) $attributes['user_id'] : null;
        $groupId = isset($attributes['group_id']) ? (int) $attributes['group_id'] : null;
        $displayName = trim((string) ($attributes['display_name'] ?? ''));

        if ($partyType === 'user') {
            if ($userId === null || ! User::query()->whereKey($userId)->exists() || $groupId !== null) {
                throw ValidationException::withMessages(['user_id' => 'A user party requires exactly one existing user.']);
            }
        } elseif ($partyType === 'group') {
            if ($groupId === null || ! Group::query()->whereKey($groupId)->exists() || $userId !== null) {
                throw ValidationException::withMessages(['group_id' => 'A group party requires exactly one existing group.']);
            }
        } elseif ($userId !== null || $groupId !== null) {
            throw ValidationException::withMessages(['party_type' => 'External parties cannot reference EarthCoop user/group ids.']);
        }

        if ($displayName === '') {
            throw ValidationException::withMessages(['display_name' => 'A correspondence party requires a display name snapshot.']);
        }

        $email = isset($attributes['email']) ? trim((string) $attributes['email']) : null;
        if ($email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['email' => 'Invalid correspondence party email.']);
        }

        $party = SecretariatParty::query()->create([
            'record_id' => $record->id,
            'role' => $role,
            'party_type' => $partyType,
            'user_id' => $userId,
            'group_id' => $groupId,
            'display_name' => $displayName,
            'organization_name' => $attributes['organization_name'] ?? null,
            'email' => $email ?: null,
            'phone' => $attributes['phone'] ?? null,
            'address' => $attributes['address'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->append($record->office, $record, $actor, 'party_added', [
            'party_id' => $party->id,
            'role' => $party->role,
            'party_type' => $party->party_type,
        ]);

        return $party;
    }

    public function linkResponse(SecretariatRecord $response, SecretariatRecord $request, User $actor): void
    {
        if (! in_array($response->direction, ['outgoing', 'internal'], true)) {
            throw ValidationException::withMessages(['response' => 'A response must be outgoing or internal correspondence.']);
        }
        if (! in_array($request->direction, ['incoming', 'internal'], true)) {
            throw ValidationException::withMessages(['request' => 'Response target must be incoming or internal correspondence.']);
        }

        $this->relations->add($response, $request, 'responds_to', $actor, ['integration' => 's4_correspondence']);
    }

    /** @param array<string,mixed> $attributes */
    private function putDetails(SecretariatRecord $record, User $actor, string $direction, array $attributes): SecretariatCorrespondenceDetail
    {
        $channel = $attributes['channel'] === null || $attributes['channel'] === ''
            ? null
            : (string) $attributes['channel'];
        if ($channel !== null && ! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages(['channel' => 'Unsupported correspondence channel.']);
        }

        if ($direction === 'incoming' && empty($attributes['received_at'])) {
            throw ValidationException::withMessages(['received_at' => 'Incoming correspondence requires a received timestamp.']);
        }
        if ($direction === 'outgoing' && ! empty($attributes['received_at'])) {
            throw ValidationException::withMessages(['received_at' => 'Outgoing correspondence cannot declare an incoming received timestamp.']);
        }
        if ($direction === 'incoming' && ! empty($attributes['sent_at'])) {
            throw ValidationException::withMessages(['sent_at' => 'Incoming correspondence does not use the office sent timestamp.']);
        }

        return SecretariatCorrespondenceDetail::query()->create([
            'record_id' => $record->id,
            'external_reference_number' => $attributes['external_reference_number'] ?? null,
            'received_at' => $attributes['received_at'] ?? null,
            'sent_at' => $attributes['sent_at'] ?? null,
            'channel' => $channel,
            'metadata' => $attributes['metadata'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    private function assertMinimumParties(SecretariatRecord $record, string $direction): void
    {
        $senders = $record->parties()->where('role', 'sender')->count();
        $recipients = $record->parties()->where('role', 'recipient')->count();

        if ($senders < 1 || $recipients < 1) {
            throw ValidationException::withMessages([
                'parties' => "{$direction} correspondence requires at least one sender and one recipient.",
            ]);
        }
    }

    private function assertCorrespondenceDraft(SecretariatRecord $record): void
    {
        if ($record->status !== 'draft') {
            throw ValidationException::withMessages(['record' => 'Correspondence parties can only be edited while the record is a draft.']);
        }
        if (! in_array($record->record_type, ['incoming_letter', 'outgoing_letter', 'internal_correspondence'], true)) {
            throw ValidationException::withMessages(['record' => 'Parties can only be attached through the correspondence workflow.']);
        }
    }
}
