<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;

final class UserResidenceReadModel
{
    /** @var array<int, Collection<int, Location>> */
    private array $pathCache = [];

    public function canonicalEnabled(): bool
    {
        return (bool) config('location-governance.runtime_enabled', false)
            && (bool) config('location-governance.registration_enabled', false);
    }

    public function primaryResidence(User $user): ?Location
    {
        if ($user->relationLoaded('locationRelationships')) {
            $relationship = $user->locationRelationships
                ->filter(fn ($item): bool => $item->relationship_type === 'primary_residence' && $item->ended_at === null)
                ->sortByDesc(fn ($item): string => sprintf(
                    '%020d:%020d',
                    optional($item->started_at)->getTimestamp() ?? 0,
                    (int) $item->id,
                ))
                ->first();

            if ($relationship !== null) {
                return $relationship->relationLoaded('location')
                    ? $relationship->location
                    : $relationship->location()->with('type')->first();
            }
        }

        return $user->locationRelationships()
            ->with('location.type')
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first()?->location;
    }

    /** @return Collection<int, Location> */
    public function pathForUser(User $user): Collection
    {
        $location = $this->primaryResidence($user);

        return $location === null ? collect() : $this->path($location);
    }

    /** @return Collection<int, Location> */
    public function path(Location $location): Collection
    {
        if (isset($this->pathCache[$location->id])) {
            return $this->pathCache[$location->id];
        }

        $location->loadMissing('type');
        $path = app(LocationTreeResolver::class)->ancestors($location)
            ->push($location)
            ->each(fn (Location $item) => $item->loadMissing('type'))
            ->values();

        return $this->pathCache[$location->id] = $path;
    }

    /** @return array<string, string> */
    public function labelsFor(User $user): array
    {
        $byType = $this->pathForUser($user)
            ->filter(fn (Location $location): bool => $location->type?->key !== null)
            ->keyBy(fn (Location $location): string => (string) $location->type->key);

        $name = static fn (?Location $location): string => $location === null
            ? ''
            : (string) ($location->localized_names[app()->getLocale()]
                ?? $location->localized_names['fa']
                ?? $location->canonical_name
                ?? $location->name
                ?? '');

        $cityOrVillage = $byType->get('city') ?? $byType->get('village');
        $regionOrRural = $byType->get('urban_region') ?? $byType->get('rural_district');

        return [
            'country' => $name($byType->get('country')),
            'province' => $name($byType->get('province')),
            'county' => $name($byType->get('county')),
            'section' => $name($byType->get('section')),
            'city_or_village' => $name($cityOrVillage),
            'region_or_rural' => $name($regionOrRural),
            'neighborhood' => $name($byType->get('neighborhood')),
            'street' => $name($byType->get('street')),
            'alley' => $name($byType->get('alley')),
            'complex' => $name($byType->get('complex')),
            'building' => $name($byType->get('building')),
        ];
    }

    public function provinceFor(User $user): ?Location
    {
        return $this->pathForUser($user)
            ->first(fn (Location $location): bool => $location->type?->key === 'province');
    }

    public function provinceOptions(): Collection
    {
        return Location::query()
            ->where('status', 'active')
            ->whereHas('type', fn ($query) => $query->where('key', 'province'))
            ->orderBy('canonical_name')
            ->get();
    }

    /** @return array<int> */
    public function descendantIdsIncluding(Location $root): array
    {
        $ids = [(int) $root->id];
        $frontier = [(int) $root->id];

        while ($frontier !== []) {
            $children = Location::query()
                ->whereIn('parent_id', $frontier)
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($children === []) {
                break;
            }

            $new = array_values(array_diff($children, $ids));
            if ($new === []) {
                break;
            }

            array_push($ids, ...$new);
            $frontier = $new;
        }

        return $ids;
    }
}
