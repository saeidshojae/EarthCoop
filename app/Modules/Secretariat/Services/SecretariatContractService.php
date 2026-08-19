<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatContractSignatory;
use App\Modules\Secretariat\Models\SecretariatContractVersionDetail;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecretariatContractService
{
    private const CONTRACT_TYPES = ['contract', 'memorandum_of_understanding', 'agreement'];
    private const RENEWAL_MODES = ['none', 'manual', 'automatic'];

    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    /** @param array<string,mixed> $attributes */
    public function putVersionDetails(SecretariatRecordVersion $version, User $actor, array $attributes): SecretariatContractVersionDetail
    {
        $version->loadMissing('record.office');
        $record = $version->record;
        $this->assertContractRecord($record);
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
            $locked = SecretariatRecordVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
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
            $locked = SecretariatRecordVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
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
