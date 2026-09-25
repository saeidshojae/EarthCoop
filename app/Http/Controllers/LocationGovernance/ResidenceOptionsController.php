<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationSchemaType;
use App\Services\LocationGovernance\IranV2RuntimeState;
use App\Services\LocationGovernance\LocationSchemaResolver;
use App\Support\GovernanceAreaDisplayName;
use App\Support\LocationDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

final class ResidenceOptionsController extends Controller
{
    public function root(): JsonResponse
    {
        $this->assertEnabled();

        $continents = GovernanceArea::query()
            ->official()
            ->active()
            ->where('governance_type', 'continent')
            ->whereHas('parent', fn ($query) => $query->official()->active()->where('governance_type', 'global')->whereNull('parent_id'))
            ->whereHas('children', fn ($query) => $query->official()->active()->where('governance_type', 'country')->whereHas('locations', fn ($locations) => $locations->where('locations.status', 'active')->whereNotNull('locations.location_schema_id')->whereNotNull('locations.location_type_id')))
            ->orderBy('rank')
            ->orderBy('canonical_name')
            ->get();

        return $this->response($continents->map(fn (GovernanceArea $area): array => $this->serializeContinent($area)));
    }

    public function children(GovernanceArea $governanceArea): JsonResponse
    {
        $this->assertEnabled();
        abort_unless($governanceArea->area_kind === 'official' && $governanceArea->status === 'active' && $governanceArea->governance_type === 'continent', 404);

        $countries = $governanceArea->children()
            ->official()
            ->active()
            ->where('governance_type', 'country')
            ->with(['locations' => fn ($query) => $query->where('locations.status', 'active')->with(['type', 'schema'])])
            ->orderBy('rank')
            ->orderBy('canonical_name')
            ->get();

        $hasIranV2 = app(IranV2RuntimeState::class)->isActive()
            && $countries->contains(fn (GovernanceArea $country): bool =>
            $country->country_code === 'IR'
            && data_get($country->metadata, 'dataset_version') === 'v2'
        );
        if ($hasIranV2) {
            $countries = $countries->reject(fn (GovernanceArea $country): bool =>
                $country->country_code === 'IR'
                && data_get($country->metadata, 'dataset_version') !== 'v2'
            )->values();
        }

        $items = $countries->map(function (GovernanceArea $country): ?array {
            $location = $country->locations->first(fn (Location $candidate): bool =>
                $candidate->status === 'active'
                && $candidate->location_schema_id !== null
                && $candidate->location_type_id !== null
                && $candidate->parent_id === null
            );

            return $location ? $this->serializeCountryLocation($location) : null;
        })->filter()->values();

        return $this->response($items);
    }

    private function serializeContinent(GovernanceArea $area): array
    {
        return [
            'id' => $area->id,
            'identity' => 'governance:'.$area->id,
            'type_key' => 'continent',
            'label' => GovernanceAreaDisplayName::for($area),
            'status' => $area->status,
            'has_children' => true,
            'navigation_only' => true,
            'children_url' => '/location/residence/options/governance/'.$area->id.'/children',
        ];
    }

    private function serializeCountryLocation(Location $location): array
    {
        $schemaType = LocationSchemaType::query()
            ->where('location_schema_id', $location->location_schema_id)
            ->where('location_type_id', $location->location_type_id)
            ->first();
        $allowedChildTypeIds = app(LocationSchemaResolver::class)->allowedChildTypes($location)->pluck('id');
        $hasChildren = $allowedChildTypeIds->isNotEmpty() && $location->children()
            ->where('status', 'active')
            ->where('location_schema_id', $location->location_schema_id)
            ->whereIn('location_type_id', $allowedChildTypeIds)
            ->exists();

        return [
            'id' => $location->id,
            'identity' => 'location:'.$location->id,
            'type_key' => 'country',
            'label' => LocationDisplayName::for($location),
            'is_residence_endpoint' => (bool) ($schemaType?->is_residence_endpoint ?? false),
            'has_children' => $hasChildren,
            'status' => $location->status,
            'children_url' => '/location/options/'.$location->id.'/children',
        ];
    }

    private function response(Collection $items): JsonResponse
    {
        return response()->json([
            'data' => $items->values(),
            'proposals' => [],
            'allowed_types' => [],
        ]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);
        abort_unless((bool) config('location-governance.registration_enabled'), 404);
    }
}
