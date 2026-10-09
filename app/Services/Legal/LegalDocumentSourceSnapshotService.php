<?php

namespace App\Services\Legal;

use App\Models\NajmBaharAgreement;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalDocumentSourceSnapshotService
{
    /**
     * Reads the existing admin-managed parent/child editor structures.
     * The output is a deterministic JSON snapshot; no source rows are edited.
     */
    public function capture(string $sourceType, int $rootId): string
    {
        if (! in_array($sourceType, ['terms', 'najm_bahar_agreements'], true)) {
            throw ValidationException::withMessages(['source_type' => 'نوع منبع قرارداد معتبر نیست.']);
        }

        return DB::transaction(function () use ($sourceType, $rootId) {
            $isTerms = $sourceType === 'terms';
            $model = $isTerms ? Term::class : NajmBaharAgreement::class;
            $root = $model::query()->whereNull('parent_id')->findOrFail($rootId);

            $records = $model::query()->get();
            $children = $records->groupBy(fn ($item) => (string) ($item->parent_id ?? 'root'));
            $visited = [];

            $build = function ($node) use (&$build, &$visited, $children, $isTerms) {
                $key = (int) $node->id;
                if (isset($visited[$key])) {
                    throw ValidationException::withMessages(['document' => 'ساختار سند دارای حلقه یا تکرار است.']);
                }
                $visited[$key] = true;
                $nodes = $children->get((string) $key, collect());
                $nodes = $isTerms
                    ? $nodes->sortBy('id')
                    : $nodes->sort(fn ($a, $b) => [$a->order, $a->id] <=> [$b->order, $b->id]);

                return [
                    'source_id' => $key,
                    'title' => (string) $node->title,
                    'content' => (string) ($isTerms ? $node->message : $node->content),
                    'children' => $nodes->map(fn ($child) => $build($child))->values()->all(),
                ];
            };

            return json_encode([
                'format' => 'earthcoop-legal-document-v1',
                'source_type' => $sourceType,
                'root' => $build($root),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        });
    }
}
