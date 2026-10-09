<?php

namespace App\Services\Legal;

use App\Models\LegalDocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalAcceptanceService
{
    /**
     * Validate the exact versions shown to a user before recording consent.
     * Never silently swap an old displayed version for the latest revision.
     */
    public function validatePublishedVersion(string $slug, int $versionId): LegalDocumentVersion
    {
        $version = LegalDocumentVersion::with('document')->findOrFail($versionId);
        if ($version->document?->slug !== $slug || $version->status !== 'published') {
            throw ValidationException::withMessages(['legal_version' => 'نسخه انتخاب‌شده دیگر برای پذیرش معتبر نیست.']);
        }

        $publication = app(LegalDocumentPublicationService::class);
        if (! $publication->verifyIntegrity($version)) {
            throw ValidationException::withMessages(['legal_version' => 'یکپارچگی نسخه قرارداد تأیید نشد.']);
        }

        $current = $publication->current($slug, $version->language);
        if (! $current || $current->id !== $versionId) {
            throw ValidationException::withMessages(['legal_version' => 'نسخه جدیدی منتشر شده است؛ قرارداد را دوباره مطالعه کنید.']);
        }

        return $version;
    }

    public function record(int $userId, LegalDocumentVersion $version, string $context): void
    {
        if (! in_array($context, ['registration', 'membership', 'najm_bahar'], true)) {
            throw ValidationException::withMessages(['context' => 'نوع پذیرش معتبر نیست.']);
        }

        // Explicitly validate again at write time; the caller must supply the
        // exact version displayed to the user.
        $this->validatePublishedVersion($version->document()->firstOrFail()->slug, $version->id);

        DB::table('legal_document_acceptances')->insertOrIgnore([
            'user_id' => $userId,
            'legal_document_version_id' => $version->id,
            'accepted_at' => now(),
            'context' => $context,
            'locale' => $version->language,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
