<?php

namespace App\Services\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalDocumentDraftService
{
    public function __construct(private readonly LegalDocumentSourceSnapshotService $snapshots)
    {
    }

    public function createFromAdminSource(
        string $slug,
        string $title,
        string $sourceType,
        int $rootId,
        string $versionLabel,
        string $language = 'fa'
    ): LegalDocumentVersion {
        if (! preg_match('/^[a-z][a-z0-9-]{2,99}$/', $slug)
            || ! preg_match('/^[0-9]+(?:\.[0-9]+){1,3}$/', $versionLabel)
            || ! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $language)) {
            throw ValidationException::withMessages(['version' => 'شناسه یا نسخه پیش‌نویس معتبر نیست.']);
        }

        // Capture first. A failed lookup must never create an empty document.
        $snapshot = $this->snapshots->capture($sourceType, $rootId);

        return DB::transaction(function () use ($slug, $title, $sourceType, $rootId, $versionLabel, $language, $snapshot) {
            $document = LegalDocument::query()->firstOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'source_type' => $sourceType, 'source_root_id' => $rootId]
            );

            $document = LegalDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($document->source_type !== $sourceType || (int) $document->source_root_id !== $rootId) {
                throw ValidationException::withMessages(['document' => 'منبع اصلی سند با نسخه‌های ثبت‌شده همخوانی ندارد.']);
            }

            if (LegalDocumentVersion::query()->where('legal_document_id', $document->id)
                ->where('version_label', $versionLabel)->where('language', $language)->exists()) {
                throw ValidationException::withMessages(['version' => 'این شماره نسخه قبلاً ثبت شده است.']);
            }

            // Draft snapshots are preview material only. The publication service
            // will recapture or verify the approved source at the publication gate.
            return LegalDocumentVersion::query()->create([
                'legal_document_id' => $document->id,
                'version_label' => $versionLabel,
                'language' => $language,
                'status' => 'draft',
            ]);
        });
    }
}
