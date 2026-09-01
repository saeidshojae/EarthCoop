<?php

namespace App\Modules\Secretariat\Services;

use App\Modules\Secretariat\Contracts\SecretariatKnowledgeRanker;
use Illuminate\Support\Collection;

class DeterministicSecretariatKnowledgeRanker implements SecretariatKnowledgeRanker
{
    public function rank(string $query, Collection $packets): Collection
    {
        $normalizedQuery = $this->normalize($query);
        $terms = $this->terms($normalizedQuery);

        return $packets
            ->map(function (array $packet, int $index) use ($normalizedQuery, $terms): array {
                $packet['_rank_score'] = $this->score($packet, $normalizedQuery, $terms);
                $packet['_rank_index'] = $index;
                return $packet;
            })
            ->sort(function (array $left, array $right): int {
                $score = ($right['_rank_score'] <=> $left['_rank_score']);
                return $score !== 0 ? $score : ($left['_rank_index'] <=> $right['_rank_index']);
            })
            ->map(function (array $packet): array {
                unset($packet['_rank_index']);
                $packet['retrieval_score'] = (float) $packet['_rank_score'];
                unset($packet['_rank_score']);
                return $packet;
            })
            ->values();
    }

    private function score(array $packet, string $query, array $terms): float
    {
        $fields = [
            ['value' => $packet['title'] ?? '', 'weight' => 8.0],
            ['value' => $packet['subject'] ?? '', 'weight' => 5.0],
            ['value' => $packet['registry_number'] ?? '', 'weight' => 3.0],
            ['value' => $packet['excerpt'] ?? '', 'weight' => 1.0],
        ];

        $score = 0.0;
        foreach ($fields as $field) {
            $text = $this->normalize((string) $field['value']);
            if ($text === '') {
                continue;
            }

            if ($query !== '' && str_contains($text, $query)) {
                $score += 20.0 * $field['weight'];
            }

            foreach ($terms as $term) {
                $count = substr_count($text, $term);
                if ($count > 0) {
                    $score += min(5, $count) * $field['weight'];
                }
            }
        }

        return $score;
    }

    /** @return array<int,string> */
    private function terms(string $query): array
    {
        $parts = preg_split('/[^\p{L}\p{N}_-]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => mb_strlen($term) >= 2)
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
