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
        $out = [];
        foreach ($this->types() as $type => $modelClass) {
            foreach ($modelClass::query()->where('status', 0)->latest('id')->limit($limitPerType)->get() as $item) {
                $out[] = $this->analyze($type, $item, $modelClass);
            }
        }
        return $out;
    }

    public function candidate(string $type, int $id): ?array
    {
        $modelClass=$this->types()[$type]??null;
        if(!is_string($modelClass)||$id<=0)return null;
        $item=$modelClass::query()->whereKey($id)->where('status',0)->first();
        return $item?$this->analyze($type,$item,$modelClass):null;
    }

    /** @return array<string,class-string<Model>> */
    protected function types(): array
    {
        return [
            'occupational'=>OccupationalField::class,'experience'=>ExperienceField::class,
            'rural'=>Rural::class,'region'=>Region::class,'neighborhood'=>Neighborhood::class,
            'street'=>Street::class,'alley'=>Alley::class,
        ];
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
                $score = $this->similarity($normalized, $this->normalize((string) $candidate->name));
                return ['id'=>(int)$candidate->id,'name'=>(string)$candidate->name,'status'=>(int)$candidate->status,'similarity'=>$score];
            })
            ->filter(fn (array $candidate): bool => $candidate['similarity'] >= 0.78)
            ->sortByDesc('similarity')->take(3)->values()->all();

        $max = (float) collect($near)->max('similarity');
        return [
            'type'=>$type,'id'=>(int)$item->getKey(),'name'=>$name,
            'parent_id'=>is_numeric($parentId)?(int)$parentId:null,
            'recommendation'=>$max>=0.94?'review_duplicate':($max>=0.78?'review_similar':'likely_unique'),
            'duplicate_risk'=>$max>=0.94?'high':($max>=0.78?'medium':'low'),
            'similar'=>$near,
        ];
    }

    protected function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = str_replace(['ي','ك','ۀ','ة','ؤ','إ','أ'], ['ی','ک','ه','ه','و','ا','ا'], $value);
        $value = str_replace("\u{200C}", ' ', $value);
        $value = preg_replace('/[\x{200f}\x{202a}-\x{202e}]/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') return 0.0;
        if ($a === $b) return 1.0;
        if (str_contains($a, $b) || str_contains($b, $a)) {
            $min=min(mb_strlen($a),mb_strlen($b)); $max=max(mb_strlen($a),mb_strlen($b));
            return $max>0?max(0.82,$min/$max):0.0;
        }
        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $distance = $this->unicodeLevenshtein($aChars, $bChars);
        $length = max(count($aChars), count($bChars), 1);
        return max(0.0, 1.0 - ($distance / $length));
    }

    /** @param array<int,string> $a @param array<int,string> $b */
    protected function unicodeLevenshtein(array $a, array $b): int
    {
        $previous = range(0, count($b));
        foreach ($a as $i => $ca) {
            $current = [$i + 1];
            foreach ($b as $j => $cb) {
                $current[$j + 1] = min($current[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($ca === $cb ? 0 : 1));
            }
            $previous = $current;
        }
        return $previous[count($b)] ?? count($a);
    }
}
