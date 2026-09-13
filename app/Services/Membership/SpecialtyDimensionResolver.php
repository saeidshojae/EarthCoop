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
        $fieldIds = collect();

        foreach ($user->experienceFields()->with('parent.parent')->get() as $field) {
            $current = $field;
            $visited = [];

            while ($current !== null && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                $fieldIds->push((int) $current->id);
                $current = $current->parent;
            }
        }

        return $fieldIds
            ->unique()
            ->sort()
            ->values()
            ->map(static fn (int $id): string => 'experience_field:'.$id);
    }
}
