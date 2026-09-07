<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatIntegrityManifest;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SecretariatIntegrityService
{
    public function __construct(private readonly SecretariatAuditService $audit) {}

    public function generate(SecretariatRecordVersion $version, User $actor): SecretariatIntegrityManifest
    {
        $version = $version->fresh([
            'record.office',
            'attachments',
            'contractDetails',
            'contractSignatories.party',
        ]);
        if (! $version || ! $version->is_official) {
            throw ValidationException::withMessages(['version' => 'Integrity manifests can only be generated for official Secretariat versions.']);
        }

        // Integrity evidence is a formal side effect, not a passive read.
        Gate::forUser($actor)->authorize('transition', $version->record);

        return DB::transaction(function () use ($version, $actor) {
            $locked = SecretariatRecordVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_official) {
                throw ValidationException::withMessages(['version' => 'Secretariat version is no longer official.']);
            }

            $fresh = $locked->fresh([
                'record.office',
                'attachments',
                'contractDetails',
                'contractSignatories.party',
            ]);
            Gate::forUser($actor)->authorize('transition', $fresh->record);
            $payload = $this->buildPayload($fresh);
            $checksum = $this->checksum($payload);
            $sequence = (int) SecretariatIntegrityManifest::query()
                ->where('record_version_id', $fresh->id)
                ->lockForUpdate()
                ->max('manifest_sequence') + 1;

            $manifest = SecretariatIntegrityManifest::query()->create([
                'record_version_id' => $fresh->id,
                'manifest_sequence' => $sequence,
                'manifest_checksum' => $checksum,
                'payload' => $payload,
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);

            $this->audit->append($fresh->record->office, $fresh->record, $actor, 'integrity_manifest_generated', [
                'version_id' => $fresh->id,
                'manifest_id' => $manifest->id,
                'manifest_sequence' => $sequence,
                'manifest_checksum' => $checksum,
            ]);

            return $manifest;
        });
    }

    /** @return array{stored_payload_valid:bool,current_version_matches:bool} */
    public function verify(SecretariatIntegrityManifest $manifest): array
    {
        $manifest = $manifest->fresh(['version.record.office']);
        $storedPayload = is_array($manifest->payload) ? $manifest->payload : [];
        $storedValid = hash_equals((string) $manifest->manifest_checksum, $this->checksum($storedPayload));

        $version = $manifest->version->fresh([
            'record.office',
            'attachments',
            'contractDetails',
            'contractSignatories.party',
        ]);
        $currentChecksum = $this->checksum($this->buildPayload($version));

        return [
            'stored_payload_valid' => $storedValid,
            'current_version_matches' => $storedValid && hash_equals((string) $manifest->manifest_checksum, $currentChecksum),
        ];
    }

    /** @return array<string,mixed> */
    public function buildPayload(SecretariatRecordVersion $version): array
    {
        $record = $version->record;
        $attachments = $version->attachments
            ->sortBy('id')
            ->map(fn ($attachment) => [
                'id' => (int) $attachment->id,
                'name' => (string) $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'file_size' => (int) $attachment->file_size,
                'checksum' => (string) $attachment->checksum,
            ])->values()->all();

        $details = $version->contractDetails;
        $signatories = $version->contractSignatories
            ->sortBy([['signing_order', 'asc'], ['id', 'asc']])
            ->map(function ($signatory) {
                $party = $signatory->party;
                return [
                    'signatory_id' => (int) $signatory->id,
                    'party_id' => (int) $signatory->party_id,
                    'party_type' => $party?->party_type,
                    'display_name' => $party?->display_name,
                    'organization_name' => $party?->organization_name,
                    'capacity' => (string) $signatory->capacity,
                    'title' => $signatory->title,
                    'signing_order' => (int) $signatory->signing_order,
                ];
            })->values()->all();

        return [
            'schema' => 'earthcoop.secretariat.integrity.v1',
            'record' => [
                'id' => (int) $record->id,
                'office_id' => (int) $record->office_id,
                'record_type' => (string) $record->record_type,
                'registry_number' => $record->registry_number,
            ],
            'version' => [
                'id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'content_checksum' => (string) $version->content_checksum,
                'approved_at' => optional($version->approved_at)->toIso8601String(),
            ],
            'attachments' => $attachments,
            'contract' => $details ? [
                'effective_at' => optional($details->effective_at)->toIso8601String(),
                'expires_at' => optional($details->expires_at)->toIso8601String(),
                'renewal_mode' => (string) $details->renewal_mode,
                'renewal_notice_days' => $details->renewal_notice_days === null ? null : (int) $details->renewal_notice_days,
                'governing_law' => $details->governing_law,
                'jurisdiction' => $details->jurisdiction,
            ] : null,
            'signatories' => $signatories,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function checksum(array $payload): string
    {
        $canonical = $this->canonicalize($payload);
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        if (! $isList) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
