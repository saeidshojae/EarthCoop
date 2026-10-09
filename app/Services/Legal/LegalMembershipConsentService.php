<?php

namespace App\Services\Legal;

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
        foreach (['membership', 'terms'] as $slug) {
            $version = $service->validatePublishedVersion($slug, (int) $ids[$slug]);
            $service->record($userId, $version, $context);
        }
    }
}
