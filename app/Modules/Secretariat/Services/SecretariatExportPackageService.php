<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatExportPackage;
use App\Modules\Secretariat\Models\SecretariatIntegrityManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;
use ZipArchive;

class SecretariatExportPackageService
{
    public function __construct(
        private readonly SecretariatIntegrityService $integrity,
        private readonly SecretariatAuditService $audit,
    ) {}

    public function generate(SecretariatIntegrityManifest $manifest, User $actor, ?string $disk = null): SecretariatExportPackage
    {
        $manifest = $manifest->fresh(['version.record.office', 'attestations']);
        if (! $manifest || ! $manifest->version?->is_official) {
            throw ValidationException::withMessages(['manifest' => 'Export requires an integrity manifest of an official version.']);
        }

        // Export exposes the full official package, so service-level view
        // authorization is mandatory even when a caller forgot controller checks.
        Gate::forUser($actor)->authorize('view', $manifest->version->record);

        $verification = $this->integrity->verify($manifest);
        if (! $verification['stored_payload_valid'] || ! $verification['current_version_matches']) {
            throw ValidationException::withMessages(['manifest' => 'Integrity manifest does not match the current official package.']);
        }

        $version = $manifest->version->fresh(['record.office', 'attachments']);
        $disk ??= (string) config('filesystems.default', 'local');
        $tmp = tempnam(sys_get_temp_dir(), 'earthcoop-secretariat-export-');
        if ($tmp === false) {
            throw new LogicException('Unable to allocate temporary export package file.');
        }
        @unlink($tmp);
        $tmpZip = $tmp . '.zip';

        $packageManifest = $this->packageManifest($manifest);
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new LogicException('Unable to create Secretariat export package archive.');
        }

        try {
            $zip->addFromString('package-manifest.json', $this->json($packageManifest));
            $zip->addFromString('integrity-manifest.json', $this->json([
                'manifest_id' => (int) $manifest->id,
                'manifest_sequence' => (int) $manifest->manifest_sequence,
                'manifest_checksum' => (string) $manifest->manifest_checksum,
                'payload' => $manifest->payload,
            ]));
            $zip->addFromString('record-version.json', $this->json([
                'record_id' => (int) $version->record_id,
                'record_type' => (string) $version->record->record_type,
                'registry_number' => $version->record->registry_number,
                'version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'title' => (string) $version->title,
                'subject' => $version->subject,
                'summary' => $version->summary,
                'body' => $version->body,
                'content_checksum' => (string) $version->content_checksum,
                'approved_at' => optional($version->approved_at)->toIso8601String(),
            ]));
            $zip->addFromString('signature-attestations.json', $this->json(
                $manifest->attestations->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'attestation_type' => (string) $item->attestation_type,
                    'provider' => (string) $item->provider,
                    'provider_reference' => $item->provider_reference,
                    'signer_name_snapshot' => (string) $item->signer_name_snapshot,
                    'verification_status' => (string) $item->verification_status,
                    'verified_at' => optional($item->verified_at)->toIso8601String(),
                ])->values()->all()
            ));

            foreach ($version->attachments as $attachment) {
                $sourceDisk = Storage::disk($attachment->storage_disk);
                if (! $sourceDisk->exists($attachment->storage_key)) {
                    throw ValidationException::withMessages(['attachment' => "Attachment #{$attachment->id} is missing from storage; export aborted."]);
                }

                $contents = $sourceDisk->get($attachment->storage_key);
                if (! hash_equals((string) $attachment->checksum, hash('sha256', $contents))) {
                    throw ValidationException::withMessages(['attachment' => "Attachment #{$attachment->id} checksum mismatch; export aborted."]);
                }

                $safeName = Str::slug(pathinfo((string) $attachment->original_name, PATHINFO_FILENAME));
                $safeName = $safeName !== '' ? $safeName : 'file';
                $extension = pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION);
                $entry = 'attachments/' . $attachment->id . '-' . $safeName . ($extension !== '' ? '.' . $extension : '');
                $zip->addFromString($entry, $contents);
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($tmpZip);
            throw $exception;
        }

        if (! $zip->close() || ! is_file($tmpZip)) {
            @unlink($tmpZip);
            throw new LogicException('Unable to finalize Secretariat export package archive.');
        }

        $packageChecksum = hash_file('sha256', $tmpZip);
        $fileSize = filesize($tmpZip);
        if ($packageChecksum === false || $fileSize === false) {
            @unlink($tmpZip);
            throw new LogicException('Unable to checksum finalized Secretariat export package.');
        }

        $storageKey = sprintf(
            'secretariat/exports/%d/%d/%s.zip',
            $version->record->office_id,
            $version->record_id,
            (string) Str::uuid()
        );
        $stream = fopen($tmpZip, 'rb');
        if ($stream === false || ! Storage::disk($disk)->put($storageKey, $stream)) {
            if (is_resource($stream)) fclose($stream);
            @unlink($tmpZip);
            throw new LogicException('Unable to persist Secretariat export package.');
        }
        if (is_resource($stream)) fclose($stream);
        @unlink($tmpZip);

        try {
            return DB::transaction(function () use ($manifest, $version, $actor, $disk, $storageKey, $fileSize, $packageChecksum, $packageManifest) {
                $lockedManifest = SecretariatIntegrityManifest::query()
                    ->with('version.record.office')
                    ->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('view', $lockedManifest->version->record);

                $verification = $this->integrity->verify($lockedManifest);
                if (! $verification['stored_payload_valid'] || ! $verification['current_version_matches']) {
                    throw ValidationException::withMessages(['manifest' => 'Integrity package changed before export commit.']);
                }

                $sequence = (int) SecretariatExportPackage::query()
                    ->where('record_version_id', $version->id)
                    ->lockForUpdate()
                    ->max('package_sequence') + 1;

                $package = SecretariatExportPackage::query()->create([
                    'record_version_id' => $version->id,
                    'integrity_manifest_id' => $lockedManifest->id,
                    'package_sequence' => $sequence,
                    'format' => 'zip',
                    'storage_disk' => $disk,
                    'storage_key' => $storageKey,
                    'file_size' => (int) $fileSize,
                    'package_checksum' => $packageChecksum,
                    'package_manifest' => $packageManifest,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                ]);

                $this->audit->append($version->record->office, $version->record, $actor, 'export_package_generated', [
                    'export_package_id' => $package->id,
                    'manifest_id' => $lockedManifest->id,
                    'package_sequence' => $sequence,
                    'package_checksum' => $packageChecksum,
                ]);

                return $package;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storageKey);
            throw $exception;
        }
    }

    /** @return array{storage_present:bool,package_checksum_valid:bool,integrity_valid:bool} */
    public function verify(SecretariatExportPackage $package): array
    {
        $package = $package->fresh(['integrityManifest']);
        $disk = Storage::disk($package->storage_disk);
        $present = $disk->exists($package->storage_key);
        $checksumValid = false;
        if ($present) {
            $contents = $disk->get($package->storage_key);
            $checksumValid = hash_equals((string) $package->package_checksum, hash('sha256', $contents));
        }
        $integrity = $this->integrity->verify($package->integrityManifest);

        return [
            'storage_present' => $present,
            'package_checksum_valid' => $checksumValid,
            'integrity_valid' => $integrity['stored_payload_valid'] && $integrity['current_version_matches'],
        ];
    }

    /** @return array<string,mixed> */
    private function packageManifest(SecretariatIntegrityManifest $manifest): array
    {
        $version = $manifest->version->fresh(['record', 'attachments']);
        return [
            'schema' => 'earthcoop.secretariat.export.v1',
            'record_id' => (int) $version->record_id,
            'registry_number' => $version->record->registry_number,
            'version_id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'integrity_manifest_id' => (int) $manifest->id,
            'integrity_manifest_checksum' => (string) $manifest->manifest_checksum,
            'attachments' => $version->attachments->sortBy('id')->map(fn ($attachment) => [
                'id' => (int) $attachment->id,
                'name' => (string) $attachment->original_name,
                'checksum' => (string) $attachment->checksum,
                'file_size' => (int) $attachment->file_size,
            ])->values()->all(),
        ];
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
