<?php

namespace App\Services\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Models\AgeGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgeDimensionResolver implements DimensionResolver
{
    public function dimensionKey(): string
    {
        return 'age';
    }

    public function valuesFor(User $user): Collection
    {
        if ($user->birth_date === null) {
            return collect();
        }

        $age = Carbon::parse($user->birth_date)->age;
        $ageGroup = AgeGroup::query()
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age)
            ->orderBy('id')
            ->first();

        return $ageGroup === null
            ? collect()
            : collect(['age_group:'.(int) $ageGroup->id]);
    }
}
