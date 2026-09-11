<?php

namespace App\Services\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Models\User;
use Illuminate\Support\Collection;

class GenderDimensionResolver implements DimensionResolver
{
    public function dimensionKey(): string
    {
        return 'gender';
    }

    public function valuesFor(User $user): Collection
    {
        $gender = trim((string) $user->gender);

        return $gender === ''
            ? collect()
            : collect(['gender:'.strtolower($gender)]);
    }
}
