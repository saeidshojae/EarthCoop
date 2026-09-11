<?php

namespace App\Services\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Models\User;
use Illuminate\Support\Collection;

class PublicDimensionResolver implements DimensionResolver
{
    public function dimensionKey(): string
    {
        return 'public';
    }

    public function valuesFor(User $user): Collection
    {
        return collect(['public']);
    }
}
