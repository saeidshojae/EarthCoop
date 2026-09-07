<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Contracts\SecretariatKnowledgeRanker;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SecretariatKnowledgeRetrievalService
{
    private const QUERY_STOPWORDS = [
        'در', 'از', 'به', 'با', 'برای', 'درباره', 'روی', 'چه', 'چی', 'چیست', 'کدام', 'آیا',
        'این', 'آن', 'را', 'و', 'یا', 'که', 'یک', 'است', 'هست', 'بود', 'شده', 'شود', 'کرده',
        'لطفا', 'لطفاً', 'میخواهم', 'می‌خواهم', 'میشه', 'می‌شود', 'کن', 'بگو', 'بده',
        'سند', 'اسناد', 'رسمی', 'دبیرخانه', 'جستجو', 'جست‌وجو', 'بگرد', 'پیدا',
        'the', 'a', 'an', 'in', 'on', 'of', 'to', 'for', 'about', 'find', 'search', 'document', 'documents',
    ];

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
     * Natural-language candidate generation fans out only through the already
     * permission-aware SecretariatSearchService. Ranking then happens only after
     * authorized packets are built. A semantic/vector implementation may replace
     * the ranker contract later, but it must never load raw Secretariat records
     * or broaden candidate authority.
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

        unset($filters['text']);
        $candidateLimit = min(100, max(20, $limit * 8));
        $records = $this->knowledgeCandidates($actor, $query, $filters, $candidateLimit);
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

    /** @return Collection<int,SecretariatRecord> */
    private function knowledgeCandidates(User $actor, string $query, array $filters, int $candidateLimit): Collection
    {
        $needles = collect([$query])
            ->merge($this->queryTerms($query))
            ->map(fn (string $value) => trim($value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->take(10)
            ->values();

        $perSearchLimit = min(40, max(10, $candidateLimit));
        $records = collect();

        foreach ($needles as $needle) {
            $result = $this->search->search(
                $actor,
                array_merge($filters, ['text' => $needle]),
                $perSearchLimit,
            );

            foreach ($result as $record) {
                $records->put((int) $record->id, $record);
            }

            if ($records->count() >= $candidateLimit) {
                break;
            }
        }

        return $records->values()->take($candidateLimit);
    }

    /** @return array<int,string> */
    private function queryTerms(string $query): array
    {
        $normalized = mb_strtolower(preg_replace('/[\x{200C}\x{200F}\x{202A}-\x{202E}]/u', ' ', $query) ?? $query);
        $parts = preg_split('/[^\p{L}\p{N}_-]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopwords = array_fill_keys(self::QUERY_STOPWORDS, true);

        return collect($parts)
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => mb_strlen($term) >= 2 && ! isset($stopwords[$term]))
            ->unique()
            ->take(9)
            ->values()
            ->all();
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
