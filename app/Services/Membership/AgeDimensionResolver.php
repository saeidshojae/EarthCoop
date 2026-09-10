<?php

namespace App\Services\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Models\User;
use Illuminate\Support\Collection;

class AgeDimensionResolver implements DimensionResolver
{
    public function dimensionKey(): string
    {
        return 'age';
    }

    public function valuesFor(User $user): Collection
    {
        $ageGroup = $user->ageGroup;

        return $ageGroup === null
            ? collect()
            : collect(['age_group:'.(int) $ageGroup->id]);
    }
}
