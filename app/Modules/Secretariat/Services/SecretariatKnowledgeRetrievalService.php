<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SecretariatKnowledgeRetrievalService
{
    public function __construct(
        private readonly SecretariatSearchService $search,
        private readonly SecretariatAclService $acl,
    ) {
    }

    /**
     * Build permission-safe knowledge packets for Najm Hoda or another retrieval
     * consumer. Consumers never query Secretariat records directly: candidate
     * selection and the final RecordPolicy check stay inside Secretariat.
     *
     * This S6 boundary is deliberately deterministic. It does not create a
     * global vector index and it does not grant an AI actor any independent
     * authority. A future semantic ranker may only rank packets returned here or
     * consume an equally strict pre-authorized candidate boundary.
     */
    public function retrieve(
        User $actor,
        string $query,
        array $filters = [],
        int $limit = 8,
        int $perRecordChars = 4000,
        int $totalChars = 16000,
    ): Collection {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) > 2000) {
            throw ValidationException::withMessages([
                'query' => 'Knowledge retrieval requires a non-empty query up to 2000 characters.',
            ]);
        }

        $limit = max(1, min(25, $limit));
        $perRecordChars = max(256, min(12000, $perRecordChars));
        $totalChars = max($perRecordChars, min(50000, $totalChars));

        $filters['text'] = $query;
        $records = $this->search->search($actor, $filters, $limit);
        $remaining = $totalChars;
        $queryFingerprint = hash('sha256', $query);
        $packets = collect();

        foreach ($records as $record) {
            if ($remaining <= 0) {
                break;
            }

            /** @var SecretariatRecord $record */
            $record->loadMissing(['office', 'currentVersion']);
            $content = $this->contentFor($record);
            $allowedChars = min($perRecordChars, $remaining);
            $excerpt = mb_substr($content, 0, $allowedChars);
            $remaining -= mb_strlen($excerpt);

            if ($record->confidentiality === 'confidential') {
                $this->acl->auditSensitiveAccess($record, $actor, [
                    'channel' => 'knowledge_retrieval',
                    'query_fingerprint' => $queryFingerprint,
                ]);
            }

            $packets->push([
                'record_id' => (int) $record->id,
                'office_id' => (int) $record->office_id,
                'office_code' => $record->office?->code,
                'registry_number' => $record->registry_number,
                'record_type' => $record->record_type,
                'confidentiality' => $record->confidentiality,
                'source_type' => $record->source_type,
                'source_id' => $record->source_id,
                'title' => $record->title,
                'subject' => $record->subject,
                'excerpt' => $excerpt,
                'truncated' => mb_strlen($content) > mb_strlen($excerpt),
            ]);
        }

        return $packets;
    }

    private function contentFor(SecretariatRecord $record): string
    {
        $version = $record->currentVersion;
        $parts = [
            $version?->title ?? $record->title,
            $version?->subject ?? $record->subject,
            $version?->summary ?? $record->summary,
            $version?->body,
        ];

        return collect($parts)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->implode("\n\n");
    }
}
