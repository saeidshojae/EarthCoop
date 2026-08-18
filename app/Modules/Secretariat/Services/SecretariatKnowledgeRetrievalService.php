<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Contracts\SecretariatKnowledgeRanker;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SecretariatKnowledgeRetrievalService
{
    public function __construct(
        private readonly SecretariatSearchService $search,
        private readonly SecretariatAclService $acl,
        private readonly SecretariatKnowledgeRanker $ranker,
    ) {
    }

    /**
     * Build permission-safe knowledge packets for Najm Hoda or another retrieval
     * consumer. Consumers never query Secretariat records directly: candidate
     * selection and the final RecordPolicy check stay inside Secretariat.
     *
     * Ranking happens only after authorized packets are built. A semantic/vector
     * implementation may replace the ranker contract later, but it must never
     * load raw Secretariat records or broaden candidate authority.
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
        $candidateLimit = min(100, max($limit, $limit * 4));
        $records = $this->search->search($actor, $filters, $candidateLimit);
        $queryFingerprint = hash('sha256', $query);
        $candidatePackets = collect();

        foreach ($records as $record) {
            /** @var SecretariatRecord $record */
            $record->loadMissing(['office', 'currentVersion']);
            $content = $this->contentFor($record);

            $candidatePackets->push([
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
                'excerpt' => mb_substr($content, 0, $perRecordChars),
                'truncated' => mb_strlen($content) > $perRecordChars,
            ]);
        }

        $ranked = $this->ranker->rank($query, $candidatePackets)->take($limit)->values();
        $remaining = $totalChars;
        $packets = collect();

        foreach ($ranked as $packet) {
            if ($remaining <= 0) {
                break;
            }

            $excerpt = (string) ($packet['excerpt'] ?? '');
            $allowedChars = min(mb_strlen($excerpt), $remaining);
            $boundedExcerpt = mb_substr($excerpt, 0, $allowedChars);
            $remaining -= mb_strlen($boundedExcerpt);

            $packet['truncated'] = (bool) ($packet['truncated'] ?? false)
                || mb_strlen($excerpt) > mb_strlen($boundedExcerpt);
            $packet['excerpt'] = $boundedExcerpt;

            if (($packet['confidentiality'] ?? null) === 'confidential') {
                $record = $records->firstWhere('id', (int) $packet['record_id']);
                if ($record instanceof SecretariatRecord) {
                    $this->acl->auditSensitiveAccess($record, $actor, [
                        'channel' => 'knowledge_retrieval',
                        'query_fingerprint' => $queryFingerprint,
                    ]);
                }
            }

            $packets->push($packet);
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
