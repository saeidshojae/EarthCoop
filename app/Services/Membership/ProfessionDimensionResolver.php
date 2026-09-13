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
        $fieldIds = collect();

        foreach ($user->occupationalFields()->with('parent.parent')->get() as $field) {
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
            ->map(static fn (int $id): string => 'occupational_field:'.$id);
    }
}
