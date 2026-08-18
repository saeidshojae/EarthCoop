<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SecretariatSearchService
{
    private const TYPES = [
        'incoming_letter',
        'outgoing_letter',
        'internal_correspondence',
        'meeting_minute',
        'resolution',
        'formal_decision',
        'contract',
        'memorandum_of_understanding',
        'agreement',
        'policy',
        'directive',
        'official_report',
        'notice',
        'official_note',
        'financial_record',
        'execution_record',
        'election_record',
        'case_record',
        'other',
    ];

    /**
     * S2 quick deterministic search.
     *
     * This is intentionally a bounded Collection result rather than a semantic
     * or globally paginated index. Every candidate is policy-filtered before it
     * can leave the service, so restricted/confidential metadata cannot bypass
     * SecretariatRecordPolicy through search. S6 will replace this with a
     * scalable permission-pre-filtered retrieval layer.
     */
    public function search(User $actor, array $filters = [], int $limit = 50): Collection
    {
        $limit = max(1, min(100, $limit));
        $this->validateFilters($filters);

        $query = SecretariatRecord::query()->with(['office', 'currentVersion']);

        if (! empty($filters['office_id'])) {
            $query->where('office_id', (int) $filters['office_id']);
        }
        if (! empty($filters['registry_number'])) {
            $query->where('registry_number', 'like', '%' . $this->escapeLike((string) $filters['registry_number']) . '%');
        }
        if (! empty($filters['record_type'])) {
            $query->where('record_type', (string) $filters['record_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['title'])) {
            $term = '%' . $this->escapeLike((string) $filters['title']) . '%';
            $query->where(function ($nested) use ($term) {
                $nested->where('title', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('registered_at', '>=', (string) $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('registered_at', '<=', (string) $filters['date_to']);
        }

        // Pull a bounded oversample because S2 applies the authoritative Policy
        // after deterministic DB filters. This avoids leaking forbidden records
        // while keeping the implementation small until S6 builds DB-level ACL
        // pre-filtering for scalable pagination/semantic retrieval.
        return $query
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->limit(min(500, $limit * 5))
            ->get()
            ->filter(fn (SecretariatRecord $record): bool => $actor->can('view', $record))
            ->take($limit)
            ->values();
    }

    private function validateFilters(array $filters): void
    {
        if (isset($filters['record_type']) && $filters['record_type'] !== ''
            && ! in_array((string) $filters['record_type'], self::TYPES, true)) {
            throw ValidationException::withMessages(['record_type' => 'Unsupported Secretariat search record type.']);
        }

        foreach (['office_id'] as $positiveId) {
            if (isset($filters[$positiveId]) && $filters[$positiveId] !== '' && (int) $filters[$positiveId] < 1) {
                throw ValidationException::withMessages([$positiveId => 'Secretariat search id must be positive.']);
            }
        }

        foreach (['registry_number', 'title', 'status'] as $stringField) {
            if (isset($filters[$stringField]) && mb_strlen((string) $filters[$stringField]) > 500) {
                throw ValidationException::withMessages([$stringField => 'Secretariat search filter is too long.']);
            }
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    }
}
