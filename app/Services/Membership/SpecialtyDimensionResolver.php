<?php

namespace App\Services\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Models\User;
use Illuminate\Support\Collection;

class SpecialtyDimensionResolver implements DimensionResolver
{
    public function dimensionKey(): string
    {
        return 'specialty';
    }

    public function valuesFor(User $user): Collection
    {
        return $user->experienceFields()
            ->pluck('experience_fields.id')
            ->map(static fn ($id): string => 'experience_field:'.(int) $id)
            ->unique()
            ->sort()
            ->values();
    }
}
