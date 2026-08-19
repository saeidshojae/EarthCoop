<?php

namespace App\Modules\Secretariat\Services;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatContractSignatory;
use App\Modules\Secretariat\Models\SecretariatContractVersionDetail;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SecretariatContractService
{
    private const CONTRACT_TYPES = ['contract', 'memorandum_of_understanding', 'agreement'];
    private const RENEWAL_MODES = ['none', 'manual', 'automatic'];
    private const PARTY_TYPES = ['user', 'group', 'external'];

    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    /** @param array<string,mixed> $attributes */
    public function addParty(SecretariatRecord $record, User $actor, array $attributes): SecretariatParty
    {
        $record->loadMissing('office');
        $this->assertContractRecord($record);
        $this->authorizeMutation($record, $actor);
        if (in_array((string) $record->status, ['archived', 'voided'], true)) {
            throw ValidationException::withMessages(['record' => 'Archived or voided contracts cannot receive new party snapshots.']);
        }

        $partyType = (string) ($attributes['party_type'] ?? '');
        if (! in_array($partyType, self::PARTY_TYPES, true)) {
            throw ValidationException::withMessages(['party_type' => 'Unsupported contract party type.']);
        }

        $userId = isset($attributes['user_id']) ? (int) $attributes['user_id'] : null;
        $groupId = isset($attributes['group_id']) ? (int) $attributes['group_id'] : null;
        if ($partyType === 'user') {
            if ($userId === null || ! User::query()->whereKey($userId)->exists() || $groupId !== null) {
                throw ValidationException::withMessages(['user_id' => 'A user contract party requires exactly one existing user.']);
            }
        } elseif ($partyType === 'group') {
            if ($groupId === null || ! Group::query()->whereKey($groupId)->exists() || $userId !== null) {
                throw ValidationException::withMessages(['group_id' => 'A group contract party requires exactly one existing group.']);
            }
        } elseif ($userId !== null || $groupId !== null) {
            throw ValidationException::withMessages(['party_type' => 'External contract parties cannot reference EarthCoop user/group ids.']);
        }

        $displayName = trim((string) ($attributes['display_name'] ?? ''));
        if ($displayName === '' || mb_strlen($displayName) > 255) {
            throw ValidationException::withMessages(['display_name' => 'A contract party requires a display name up to 255 characters.']);
        }

        $email = $this->nullableString($attributes['email'] ?? null, 320);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['email' => 'Invalid contract party email.']);
        }

        $party = SecretariatParty::query()->create([
            'record_id' => $record->id,
            'role' => 'other',
            'party_type' => $partyType,
            'user_id' => $userId,
            'group_id' => $groupId,
            'display_name' => $displayName,
            'organization_name' => $this->nullableString($attributes['organization_name'] ?? null, 255),
            'email' => $email,
            'phone' => $this->nullableString($attributes['phone'] ?? null, 80),
            'address' => $this->nullableString($attributes['address'] ?? null, 2000),
            'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
            'created_by' => $actor->id,
        ]);

        $this->audit->append($record->office, $record, $actor, 'contract_party_added', [
            'party_id' => $party->id,
            'party_type' => $party->party_type,
        ]);

        return $party;
    }

    /** @param array<string,mixed> $attributes */
    public function putVersionDetails(SecretariatRecordVersion $version, User $actor, array $attributes): SecretariatContractVersionDetail
    {
        $version->loadMissing('record.office');
        $record = $version->record;
        $this->assertContractRecord($record);
        $this->authorizeMutation($record, $actor);
        $this->assertMutableVersion($version);

        $effectiveAt = $attributes['effective_at'] ?? null;
        $expiresAt = $attributes['expires_at'] ?? null;
        $renewalMode = (string) ($attributes['renewal_mode'] ?? 'none');
        $noticeDays = array_key_exists('renewal_notice_days', $attributes) && $attributes['renewal_notice_days'] !== null
            ? (int) $attributes['renewal_notice_days']
            : null;

        if (! in_array($renewalMode, self::RENEWAL_MODES, true)) {
            throw ValidationException::withMessages(['renewal_mode' => 'Unsupported contract renewal mode.']);
        }
        if ($noticeDays !== null && ($noticeDays < 0 || $noticeDays > 3650)) {
            throw ValidationException::withMessages(['renewal_notice_days' => 'Renewal notice days must be between 0 and 3650.']);
        }
        if ($renewalMode === 'none' && $noticeDays !== null) {
            throw ValidationException::withMessages(['renewal_notice_days' => 'A non-renewing contract cannot define renewal notice days.']);
        }
        if ($effectiveAt !== null && $expiresAt !== null && strtotime((string) $expiresAt) <= strtotime((string) $effectiveAt)) {
            throw ValidationException::withMessages(['expires_at' => 'Contract expiry must be after its effective date.']);
        }

        return DB::transaction(function () use ($version, $record, $actor, $attributes, $effectiveAt, $expiresAt, $renewalMode, $noticeDays) {
            $locked = SecretariatRecordVersion::query()->with('record.office')->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutation($locked->record, $actor);
            $this->assertMutableVersion($locked);

            $detail = SecretariatContractVersionDetail::query()->firstOrNew(['record_version_id' => $locked->id]);
            $detail->fill([
                'effective_at' => $effectiveAt,
                'expires_at' => $expiresAt,
                'renewal_mode' => $renewalMode,
                'renewal_notice_days' => $noticeDays,
                'governing_law' => $this->nullableString($attributes['governing_law'] ?? null, 255),
                'jurisdiction' => $this->nullableString($attributes['jurisdiction'] ?? null, 255),
                'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
                'created_by' => $detail->exists ? $detail->created_by : $actor->id,
            ]);
            $detail->save();

            $this->audit->append($record->office, $record, $actor, 'contract_version_details_saved', [
                'version_id' => $locked->id,
                'version_number' => $locked->version_number,
                'renewal_mode' => $renewalMode,
            ]);

            return $detail->refresh();
        });
    }

    /** @param array<string,mixed> $attributes */
    public function addSignatory(SecretariatRecordVersion $version, SecretariatParty $party, User $actor, array $attributes): SecretariatContractSignatory
    {
        $version->loadMissing('record.office');
        $record = $version->record;
        $this->assertContractRecord($record);
        $this->authorizeMutation($record, $actor);
        $this->assertMutableVersion($version);

        if ((int) $party->record_id !== (int) $record->id) {
            throw ValidationException::withMessages(['party_id' => 'Contract signatory must be a party of the same Secretariat record.']);
        }

        $capacity = trim((string) ($attributes['capacity'] ?? ''));
        if ($capacity === '' || mb_strlen($capacity) > 255) {
            throw ValidationException::withMessages(['capacity' => 'Contract signatory capacity is required and must be at most 255 characters.']);
        }
        $order = (int) ($attributes['signing_order'] ?? 1);
        if ($order < 1 || $order > 1000) {
            throw ValidationException::withMessages(['signing_order' => 'Signing order must be between 1 and 1000.']);
        }

        return DB::transaction(function () use ($version, $record, $party, $actor, $attributes, $capacity, $order) {
            $locked = SecretariatRecordVersion::query()->with('record.office')->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $this->authorizeMutation($locked->record, $actor);
            $this->assertMutableVersion($locked);

            $signatory = SecretariatContractSignatory::query()->updateOrCreate(
                ['record_version_id' => $locked->id, 'party_id' => $party->id],
                [
                    'capacity' => $capacity,
                    'title' => $this->nullableString($attributes['title'] ?? null, 255),
                    'signing_order' => $order,
                    'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
                    'created_by' => $actor->id,
                ]
            );

            $this->audit->append($record->office, $record, $actor, 'contract_signatory_saved', [
                'version_id' => $locked->id,
                'party_id' => $party->id,
                'capacity' => $capacity,
                'signing_order' => $order,
            ]);

            return $signatory->refresh();
        });
    }

    public function assertVersionReadyForFormality(SecretariatRecordVersion $version): void
    {
        $version->loadMissing('record');
        $this->assertContractRecord($version->record);

        if (! SecretariatContractVersionDetail::query()->where('record_version_id', $version->id)->exists()) {
            throw ValidationException::withMessages(['contract_details' => 'Formal contract/MOU/agreement requires version-specific contract details.']);
        }

        if (! SecretariatContractSignatory::query()->where('record_version_id', $version->id)->exists()) {
            throw ValidationException::withMessages(['signatories' => 'Formal contract/MOU/agreement requires at least one signatory snapshot.']);
        }
    }

    private function authorizeMutation(SecretariatRecord $record, User $actor): void
    {
        $ability = $record->status === 'draft' ? 'update' : 'transition';
        Gate::forUser($actor)->authorize($ability, $record);
    }

    private function assertContractRecord(SecretariatRecord $record): void
    {
        if (! in_array((string) $record->record_type, self::CONTRACT_TYPES, true)) {
            throw ValidationException::withMessages(['record_type' => 'Contract formality metadata is only valid for contract, MOU, or agreement records.']);
        }
    }

    private function assertMutableVersion(SecretariatRecordVersion $version): void
    {
        if ((bool) $version->is_official) {
            throw ValidationException::withMessages(['version' => 'Official contract versions are immutable; create an amendment version instead.']);
        }
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        if ($value === '') return null;
        return mb_substr($value, 0, $max);
    }
}
