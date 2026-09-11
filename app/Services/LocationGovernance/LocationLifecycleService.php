<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationRelation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LocationLifecycleService
{
    public function rename(Location $location, array $localizedNames, User $actor): Location
    {
        return DB::transaction(function () use ($location, $localizedNames, $actor) {
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);
            $metadata = $location->metadata ?? [];
            $history = $metadata['name_history'] ?? [];

            $history[] = [
                'canonical_name' => $location->canonical_name,
                'localized_names' => $location->localized_names ?? [],
                'actor_id' => $actor->id,
                'changed_at' => now()->toIso8601String(),
            ];

            $metadata['name_history'] = $history;

            $location->localized_names = $localizedNames;
            $location->metadata = $metadata;
            $location->save();

            return $location->fresh();
        });
    }

    public function supersede(Location $old, Collection $successors, string $relationType, User $actor): void
    {
        if (!in_array($relationType, ['superseded', 'merged', 'split'], true)) {
            throw new InvalidArgumentException('Unsupported location lifecycle relation type.');
        }

        if ($successors->isEmpty()) {
            throw new InvalidArgumentException('At least one successor location is required.');
        }

        if ($relationType === 'merged' && $successors->count() !== 1) {
            throw new InvalidArgumentException('A merged location must have exactly one successor.');
        }

        if ($relationType === 'split' && $successors->count() < 2) {
            throw new InvalidArgumentException('A split location must have at least two successors.');
        }

        DB::transaction(function () use ($old, $successors, $relationType, $actor) {
            $old = Location::query()->lockForUpdate()->findOrFail($old->id);
            $effectiveAt = now();

            $old->status = $relationType;
            $old->valid_to = $effectiveAt;
            $old->save();

            foreach ($successors->unique('id') as $successor) {
                if ((int) $successor->id === (int) $old->id) {
                    throw new InvalidArgumentException('A location cannot supersede itself.');
                }

                LocationRelation::query()->updateOrCreate(
                    [
                        'from_location_id' => $old->id,
                        'to_location_id' => $successor->id,
                        'relation_type' => $relationType,
                    ],
                    [
                        'effective_at' => $effectiveAt,
                        'metadata' => ['actor_id' => $actor->id],
                    ]
                );
            }
        });
    }
}
