<?php

namespace App\Services\Legal;

use App\Models\LegalDocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LegalMembershipConsentService
{
    public function publishedPair(): array
    {
        if (! Schema::hasTable('legal_document_versions')) {
            return [];
        }
        $publisher = app(LegalDocumentPublicationService::class);
        $membership = $publisher->current('membership');
        $terms = $publisher->current('terms');
        return $membership && $terms ? ['membership' => $membership, 'terms' => $terms] : [];
    }

    /**
     * A registration flow may finish against an older snapshot only when the
     * published revision kept the same acceptance baseline (minor/notice-only).
     */
    public function canCarryForwardVersion(string $slug, int $acceptedVersionId): bool
    {
        $current = app(LegalDocumentPublicationService::class)->current($slug);
        if (! $current) {
            return false;
        }

        $accepted = LegalDocumentVersion::with('document')->find($acceptedVersionId);
        return $accepted
            && $accepted->document?->slug === $slug
            && in_array($accepted->status, ['published', 'retired'], true)
            && app(LegalDocumentPublicationService::class)->verifyIntegrity($accepted)
            && $accepted->acceptance_required_version_id !== null
            && $accepted->acceptance_required_version_id === $current->acceptance_required_version_id;
    }

    public function validateForm(Request $request): array
    {
        $pair = $this->publishedPair();
        if ($pair === []) {
            return [];
        }
        $data = $request->validate([
            'membership_version_id' => 'required|integer',
            'terms_version_id' => 'required|integer',
        ]);
        $service = app(LegalAcceptanceService::class);
        foreach ($pair as $slug => $version) {
            if ((int) $data[$slug . '_version_id'] !== $version->id) {
                throw ValidationException::withMessages(['terms' => 'نسخه جدیدی منتشر شده؛ لطفاً متن جدید را مطالعه کنید.']);
            }
            $service->validatePublishedVersion($slug, $version->id);
        }
        return array_map(fn ($version) => $version->id, $pair);
    }

    public function recordForUser(int $userId, array $ids, string $context): void
    {
        if ($ids === []) {
            return;
        }
        $service = app(LegalAcceptanceService::class);
        $publications = app(LegalDocumentPublicationService::class);
        foreach (['membership', 'terms'] as $slug) {
            $current = $publications->current($slug);
            $versionId = (int) $ids[$slug];
            if ($current && $versionId !== $current->id && $this->canCarryForwardVersion($slug, $versionId)) {
                $versionId = $current->id;
            }
            $version = $service->validatePublishedVersion($slug, $versionId);
            $service->record($userId, $version, $context);
        }
    }
}
