<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];

    public function __construct(private readonly SecretariatRecordAccessQuery $accessQuery)
    {
    }

    /**
     * Permission-aware deterministic retrieval boundary.
     *
     * S6 applies the conservative authorization scope in SQL before candidates
     * enter retrieval, then rechecks the authoritative RecordPolicy before any
     * result leaves this service. Semantic retrieval must consume this same
     * boundary (or a stricter derivative) rather than indexing/querying records
     * independently.
     */
    public function search(User $actor, array $filters = [], int $limit = 50): Collection
    {
        $limit = max(1, min(200, $limit));
        $this->validateFilters($filters);
        SecretariatMorphMap::register();

        $query = SecretariatRecord::query()->with(['office', 'currentVersion']);
        $this->accessQuery->apply($query, $actor);
        $this->applyFilters($query, $filters);

        // The SQL prefilter is intended to mirror current authorization, while
        // the Policy remains authoritative. Fetch a small bounded safety margin
        // only for rare policy/query drift (e.g. temporary role restoration),
        // not the old S2 5x oversample model.
        return $query
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->limit(min(300, $limit + 25))
            ->get()
            ->filter(fn (SecretariatRecord $record): bool => $actor->can('view', $record))
            ->take($limit)
            ->values();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['office_id'])) {
            $query->where('secretariat_records.office_id', (int) $filters['office_id']);
        }
        if (! empty($filters['registry_number'])) {
            $query->where('secretariat_records.registry_number', 'like', '%' . $this->escapeLike((string) $filters['registry_number']) . '%');
        }
        if (! empty($filters['record_type'])) {
            $query->where('secretariat_records.record_type', (string) $filters['record_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('secretariat_records.status', (string) $filters['status']);
        }
        if (! empty($filters['confidentiality'])) {
            $query->where('secretariat_records.confidentiality', (string) $filters['confidentiality']);
        }
        if (! empty($filters['title'])) {
            $term = '%' . $this->escapeLike((string) $filters['title']) . '%';
            $query->where(function (Builder $nested) use ($term) {
                $nested->where('secretariat_records.title', 'like', $term)
                    ->orWhere('secretariat_records.subject', 'like', $term);
            });
        }
        if (! empty($filters['text'])) {
            $term = '%' . $this->escapeLike((string) $filters['text']) . '%';
            $query->where(function (Builder $text) use ($term) {
                $text->where('secretariat_records.title', 'like', $term)
                    ->orWhere('secretariat_records.subject', 'like', $term)
                    ->orWhere('secretariat_records.summary', 'like', $term)
                    ->orWhereHas('currentVersion', function (Builder $version) use ($term) {
                        $version->where('title', 'like', $term)
                            ->orWhere('subject', 'like', $term)
                            ->orWhere('summary', 'like', $term)
                            ->orWhere('body', 'like', $term);
                    });
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('secretariat_records.registered_at', '>=', (string) $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('secretariat_records.registered_at', '<=', (string) $filters['date_to']);
        }
        if (! empty($filters['party'])) {
            $party = '%' . $this->escapeLike((string) $filters['party']) . '%';
            $query->whereHas('parties', function (Builder $parties) use ($party) {
                $parties->where('display_name', 'like', $party)
                    ->orWhere('organization_name', 'like', $party)
                    ->orWhere('email', 'like', $party)
                    ->orWhere('phone', 'like', $party);
            });
        }
        if (! empty($filters['party_user_id'])) {
            $query->whereHas('parties', fn (Builder $parties) => $parties->where('user_id', (int) $filters['party_user_id']));
        }
        if (! empty($filters['party_group_id'])) {
            $query->whereHas('parties', fn (Builder $parties) => $parties->where('group_id', (int) $filters['party_group_id']));
        }
        if (! empty($filters['source_type'])) {
            $query->where('secretariat_records.source_type', (string) $filters['source_type']);
        }
        if (! empty($filters['source_id'])) {
            $query->where('secretariat_records.source_id', (int) $filters['source_id']);
        }
        if (! empty($filters['case_id'])) {
            $caseId = (int) $filters['case_id'];
            $query->whereExists(function ($case) use ($caseId) {
                $case->selectRaw('1')
                    ->from('secretariat_case_records as scr_search')
                    ->whereColumn('scr_search.record_id', 'secretariat_records.id')
                    ->where('scr_search.case_id', $caseId);
            });
        }
    }

    private function validateFilters(array $filters): void
    {
        if (isset($filters['record_type']) && $filters['record_type'] !== ''
            && ! in_array((string) $filters['record_type'], self::TYPES, true)) {
            throw ValidationException::withMessages(['record_type' => 'Unsupported Secretariat search record type.']);
        }

        if (isset($filters['confidentiality']) && $filters['confidentiality'] !== ''
            && ! in_array((string) $filters['confidentiality'], self::CONFIDENTIALITIES, true)) {
            throw ValidationException::withMessages(['confidentiality' => 'Unsupported Secretariat confidentiality filter.']);
        }

        foreach (['office_id', 'party_user_id', 'party_group_id', 'source_id', 'case_id'] as $positiveId) {
            if (isset($filters[$positiveId]) && $filters[$positiveId] !== '' && (int) $filters[$positiveId] < 1) {
                throw ValidationException::withMessages([$positiveId => 'Secretariat search id must be positive.']);
            }
        }

        foreach (['registry_number', 'title', 'status', 'party'] as $stringField) {
            if (isset($filters[$stringField]) && mb_strlen((string) $filters[$stringField]) > 500) {
                throw ValidationException::withMessages([$stringField => 'Secretariat search filter is too long.']);
            }
        }
        if (isset($filters['text']) && mb_strlen((string) $filters['text']) > 2000) {
            throw ValidationException::withMessages(['text' => 'Secretariat full-text filter is too long.']);
        }

        if (! empty($filters['source_type'])) {
            $token = (string) $filters['source_type'];
            if (Relation::getMorphedModel($token) === null) {
                throw ValidationException::withMessages(['source_type' => 'Unknown or unmapped Secretariat source type.']);
            }
        }
        if (! empty($filters['source_id']) && empty($filters['source_type'])) {
            throw ValidationException::withMessages(['source_id' => 'source_id requires source_type.']);
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    }
}
