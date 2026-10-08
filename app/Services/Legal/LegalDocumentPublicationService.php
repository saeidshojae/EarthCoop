<?php

namespace App\Services\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalDocumentPublicationService
{
    public function publish(LegalDocumentVersion $version, string $snapshot, int $publisherId): LegalDocumentVersion
    {
        return DB::transaction(function () use ($version, $snapshot, $publisherId) {
            $document = LegalDocument::query()->whereKey($version->legal_document_id)->lockForUpdate()->firstOrFail();
            $locked = LegalDocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft' || $locked->content_snapshot !== null || $locked->published_at !== null) {
                throw ValidationException::withMessages(['version' => 'این نسخه قابل انتشار یا بازنویسی نیست.']);
            }

            if (trim($snapshot) === '') {
                throw ValidationException::withMessages(['content' => 'متن قرارداد نمی‌تواند خالی باشد.']);
            }

            LegalDocumentVersion::query()
                ->where('legal_document_id', $document->id)
                ->where('language', $locked->language)
                ->where('status', 'published')
                ->update(['status' => 'retired']);

            $locked->forceFill([
                'content_snapshot' => $snapshot,
                'content_sha256' => hash('sha256', $snapshot),
                'published_at' => now(),
                'published_by' => $publisherId,
                'status' => 'published',
            ])->save();

            return $locked->refresh();
        });
    }

    public function current(string $slug, string $language = 'fa'): ?LegalDocumentVersion
    {
        return LegalDocumentVersion::query()
            ->whereHas('document', fn ($query) => $query->where('slug', $slug))
            ->where('language', $language)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->first();
    }

    public function verifyIntegrity(LegalDocumentVersion $version): bool
    {
        return $version->content_snapshot !== null
            && $version->content_sha256 !== null
            && hash_equals($version->content_sha256, hash('sha256', $version->content_snapshot));
    }
}
