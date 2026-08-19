<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Alley;
use App\Models\ExperienceField;
use App\Models\Neighborhood;
use App\Models\OccupationalField;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FounderReferenceApprovalCandidateService
{
    /** @return array<int,array<string,mixed>> */
    public function candidates(int $limitPerType = 10): array
    {
        $limitPerType = max(1, min($limitPerType, 50));
        $types = [
            'occupational' => OccupationalField::class,
            'experience' => ExperienceField::class,
            'rural' => Rural::class,
            'region' => Region::class,
            'neighborhood' => Neighborhood::class,
            'street' => Street::class,
            'alley' => Alley::class,
        ];

        $out = [];
        foreach ($types as $type => $modelClass) {
            foreach ($modelClass::query()->where('status', 0)->latest('id')->limit($limitPerType)->get() as $item) {
                $out[] = $this->analyze($type, $item, $modelClass);
            }
        }
        return $out;
    }

    /** @return array<string,mixed> */
    protected function analyze(string $type, Model $item, string $modelClass): array
    {
        $name = trim((string) $item->getAttribute('name'));
        $normalized = $this->normalize($name);
        $parentId = $item->getAttribute('parent_id');

        $query = $modelClass::query()->whereKeyNot($item->getKey());
        if ($parentId !== null) $query->where('parent_id', $parentId);

        $near = $query->limit(250)->get(['id', 'name', 'status', 'parent_id'])
            ->map(function ($candidate) use ($normalized) {
                $other = $this->normalize((string) $candidate->name);
                $score = $this->similarity($normalized, $other);
                return [
                    'id' => (int) $candidate->id,
                    'name' => (string) $candidate->name,
                    'status' => (int) $candidate->status,
                    'similarity' => $score,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['similarity'] >= 0.78)
            ->sortByDesc('similarity')
            ->take(3)
            ->values()
            ->all();

        $max = (float) collect($near)->max('similarity');
        $recommendation = $max >= 0.94 ? 'review_duplicate' : ($max >= 0.78 ? 'review_similar' : 'likely_unique');

        return [
            'type' => $type,
            'id' => (int) $item->getKey(),
            'name' => $name,
            'parent_id' => is_numeric($parentId) ? (int) $parentId : null,
            'recommendation' => $recommendation,
            'duplicate_risk' => $max >= 0.94 ? 'high' : ($max >= 0.78 ? 'medium' : 'low'),
            'similar' => $near,
        ];
    }

    protected function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = str_replace(['ي','ك','ۀ','ة','ؤ','إ','أ'], ['ی','ک','ه','ه','و','ا','ا'], $value);
        $value = preg_replace('/[\x{200c}\x{200f}\x{202a}-\x{202e}]/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') return 0.0;
        if ($a === $b) return 1.0;
        if (str_contains($a, $b) || str_contains($b, $a)) {
            $min = min(mb_strlen($a), mb_strlen($b));
            $max = max(mb_strlen($a), mb_strlen($b));
            return $max > 0 ? max(0.82, $min / $max) : 0.0;
        }

        $aa = mb_convert_encoding($a, 'ASCII', 'UTF-8');
        $bb = mb_convert_encoding($b, 'ASCII', 'UTF-8');
        $distance = levenshtein($aa, $bb);
        $length = max(strlen($aa), strlen($bb), 1);
        return max(0.0, 1.0 - ($distance / $length));
    }
}
