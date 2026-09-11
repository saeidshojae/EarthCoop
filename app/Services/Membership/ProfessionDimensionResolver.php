<?php

namespace App\Services\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Models\User;
use Illuminate\Support\Collection;

class ProfessionDimensionResolver implements DimensionResolver
{
    public function dimensionKey(): string
    {
        return 'profession';
    }

    public function valuesFor(User $user): Collection
    {
        return $user->occupationalFields()
            ->pluck('occupational_fields.id')
            ->map(static fn ($id): string => 'occupational_field:'.(int) $id)
            ->unique()
            ->sort()
            ->values();
    }
}
