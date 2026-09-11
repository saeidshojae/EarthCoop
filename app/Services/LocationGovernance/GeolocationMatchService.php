<?php

namespace App\Services\LocationGovernance;

use App\Contracts\Geocoding\ReverseGeocoder;
use App\Data\Geocoding\GeolocationMatch;
use App\Data\Geocoding\ReverseGeocodeResult;
use App\Models\Location;
use Throwable;

class GeolocationMatchService
{
    public function __construct(
        private readonly ReverseGeocoder $geocoder,
        private readonly ?LocationTreeResolver $treeResolver = null,
    ) {
    }

    public function match(
        float $latitude,
        float $longitude,
        string $locale,
        ?Location $manualSelection = null,
    ): GeolocationMatch {
        try {
            $result = $this->geocoder->reverse($latitude, $longitude, $locale);
        } catch (Throwable) {
            return new GeolocationMatch(
                status: 'provider_error',
                suggestedLocationId: null,
                manualLocationId: $manualSelection?->id,
                confidence: null,
                gpsConsistent: false,
                manualSelectionAvailable: true,
            );
        }

        $suggested = $this->resolveCanonicalLocation($result);

        if ($suggested === null) {
            return new GeolocationMatch(
                status: 'unmatched',
                suggestedLocationId: null,
                manualLocationId: $manualSelection?->id,
                confidence: $result->confidence,
                gpsConsistent: false,
                manualSelectionAvailable: true,
                provider: $result->provider,
            );
        }

        $conflict = $manualSelection !== null && $manualSelection->id !== $suggested->id;

        return new GeolocationMatch(
            status: $conflict ? 'conflict' : 'matched',
            suggestedLocationId: $suggested->id,
            manualLocationId: $manualSelection?->id,
            confidence: $result->confidence,
            gpsConsistent: ! $conflict,
            manualSelectionAvailable: true,
            provider: $result->provider,
        );
    }

    private function resolveCanonicalLocation(ReverseGeocodeResult $result): ?Location
    {
        $components = collect($result->components)
            ->mapWithKeys(fn ($name, $typeKey) => [(string) $typeKey => $this->normalize((string) $name)])
            ->filter();

        if ($components->isEmpty()) {
            return null;
        }

        $typeKeys = $components->keys()->values();
        $locations = Location::query()
            ->with(['type', 'parent.type'])
            ->where('status', 'active')
            ->when($result->countryCode, fn ($query, $countryCode) => $query->where('country_code', strtoupper((string) $countryCode)))
            ->whereHas('type', fn ($query) => $query->whereIn('key', $typeKeys))
            ->get()
            ->filter(function (Location $location) use ($components): bool {
                $typeKey = $location->type?->key;

                if ($typeKey === null || ! $components->has($typeKey)) {
                    return false;
                }

                return $this->normalize((string) ($location->canonical_name ?: $location->name)) === $components->get($typeKey);
            });

        if ($locations->isEmpty()) {
            return null;
        }

        $resolver = $this->treeResolver ?? app(LocationTreeResolver::class);

        return $locations
            ->map(function (Location $candidate) use ($components, $resolver): array {
                $path = $resolver->ancestors($candidate)->push($candidate);
                $pathByType = $path
                    ->filter(fn (Location $location) => $location->type?->key !== null)
                    ->mapWithKeys(fn (Location $location) => [
                        $location->type->key => $this->normalize((string) ($location->canonical_name ?: $location->name)),
                    ]);

                $matchedComponents = $components->filter(
                    fn (string $name, string $typeKey) => $pathByType->get($typeKey) === $name
                )->count();

                $conflicts = $components->filter(
                    fn (string $name, string $typeKey) => $pathByType->has($typeKey) && $pathByType->get($typeKey) !== $name
                )->count();

                return [
                    'location' => $candidate,
                    'matched' => $matchedComponents,
                    'conflicts' => $conflicts,
                    'depth' => $path->count(),
                ];
            })
            ->filter(fn (array $scored) => $scored['conflicts'] === 0)
            ->sortByDesc(fn (array $scored) => sprintf('%05d-%05d', $scored['matched'], $scored['depth']))
            ->first()['location'] ?? null;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value, 'UTF-8');
    }
}
