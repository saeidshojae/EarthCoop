<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatAttachment;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class SecretariatAttachmentService
{
    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    public function upload(
        SecretariatRecord $record,
        User $actor,
        UploadedFile $file,
        ?SecretariatRecordVersion $version = null,
        ?string $disk = null,
        array $metadata = [],
    ): SecretariatAttachment {
        $record = $record->fresh(['office', 'currentVersion']);
        $version ??= $record->currentVersion;

        if ($version === null || (int) $version->record_id !== (int) $record->id) {
            throw ValidationException::withMessages([
                'version_id' => 'A Secretariat attachment must belong to a version of the same record.',
            ]);
        }

        if ($this->isFormal($record) && $version->is_official) {
            throw new LogicException('A file cannot be appended retroactively to an official Secretariat version; create an amendment version first.');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            throw ValidationException::withMessages(['file' => 'Uploaded Secretariat file is not readable.']);
        }

        $disk ??= (string) config('filesystems.default', 'local');
        $checksum = hash_file('sha256', $realPath);
        if ($checksum === false) {
            throw ValidationException::withMessages(['file' => 'Unable to checksum Secretariat attachment.']);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedName = (string) Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        $directory = sprintf('secretariat/%d/%d/%d', $record->office_id, $record->id, $version->id);
        $storageKey = $directory . '/' . $storedName;

        $stored = Storage::disk($disk)->putFileAs($directory, $file, $storedName);
        if ($stored === false) {
            throw new LogicException('Unable to persist Secretariat attachment.');
        }

        try {
            return DB::transaction(function () use ($record, $version, $actor, $file, $disk, $storageKey, $checksum, $metadata) {
                /** @var SecretariatRecord $locked */
                $locked = SecretariatRecord::query()->with(['office', 'currentVersion'])->whereKey($record->id)->lockForUpdate()->firstOrFail();
                /** @var SecretariatRecordVersion $lockedVersion */
                $lockedVersion = SecretariatRecordVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

                if ((int) $lockedVersion->record_id !== (int) $locked->id) {
                    throw new LogicException('Secretariat attachment version changed record ownership during upload.');
                }
                if ($this->isFormal($locked) && $lockedVersion->is_official) {
                    throw new LogicException('Secretariat record became formal before attachment commit; upload requires an amendment version.');
                }

                $attachment = SecretariatAttachment::query()->create([
                    'record_id' => $locked->id,
                    'version_id' => $lockedVersion->id,
                    'original_name' => $file->getClientOriginalName(),
                    'storage_disk' => $disk,
                    'storage_key' => $storageKey,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => (int) $file->getSize(),
                    'checksum' => $checksum,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                    'state' => 'active',
                    'metadata' => $metadata ?: null,
                ]);

                $this->audit->append($locked->office, $locked, $actor, 'attachment_added', [
                    'attachment_id' => $attachment->id,
                    'version_number' => $lockedVersion->version_number,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'checksum' => $attachment->checksum,
                ]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            // Storage write happened before the DB transaction. If DB persistence
            // fails, compensate by removing the unreferenced object.
            Storage::disk($disk)->delete($storageKey);
            throw $exception;
        }
    }

    public function deleteDraftAttachment(SecretariatAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            /** @var SecretariatAttachment $locked */
            $locked = SecretariatAttachment::query()->with('record')->whereKey($attachment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->record->status, ['draft', 'cancelled'], true)) {
                throw new LogicException('Attachments of formal Secretariat records cannot be hard-deleted.');
            }

            $disk = $locked->storage_disk;
            $key = $locked->storage_key;
            $locked->delete();

            // Object storage cannot participate in the SQL transaction. Delete
            // the physical object only after the row deletion has committed.
            DB::afterCommit(static function () use ($disk, $key): void {
                Storage::disk($disk)->delete($key);
            });
        });
    }

    private function isFormal(SecretariatRecord $record): bool
    {
        return in_array($record->status, ['registered', 'active', 'closed', 'archived', 'superseded', 'voided'], true);
    }
}
