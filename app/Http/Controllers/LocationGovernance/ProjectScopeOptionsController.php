<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Services\LocationGovernance\LocationSchemaResolver;
use App\Support\GovernanceAreaDisplayName;
use App\Support\LocationDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ProjectScopeOptionsController extends Controller
{
    public function root(): JsonResponse
    {
        $this->assertEnabled();
        $areas = GovernanceArea::query()->official()->active()->whereNull('parent_id')
            ->where('governance_type', 'global')->orderBy('rank')->orderBy('canonical_name')->get();
        return $this->response($areas->map(fn (GovernanceArea $area): array => $this->serializeArea($area))->values());
    }

    public function children(GovernanceArea $governanceArea): JsonResponse
    {
        $this->assertEnabled();
        abort_unless($governanceArea->area_kind === 'official' && $governanceArea->status === 'active', 404);
        $children = $governanceArea->children()->official()->active()
            ->with(['locations' => fn ($query) => $query->where('locations.status', 'active')->with(['type', 'schema'])])
            ->orderBy('rank')->orderBy('canonical_name')->get();

        if ($governanceArea->key === 'earthcoop-continent-asia') {
            $iranV2RuntimeEnabled = (bool) config('iran_settlement_catalog.v2_runtime_enabled', false);
            $hasIranV2 = $iranV2RuntimeEnabled
                && $children->contains(fn (GovernanceArea $child): bool =>
                    $child->country_code === 'IR'
                    && $child->governance_type === 'country'
                    && data_get($child->metadata, 'dataset_version') === 'v2'
                );
            if ($hasIranV2) {
                $children = $children->reject(fn (GovernanceArea $child): bool =>
                    $child->country_code === 'IR'
                    && $child->governance_type === 'country'
                    && data_get($child->metadata, 'dataset_version') !== 'v2'
                )->values();
            } elseif (! $iranV2RuntimeEnabled) {
                $children = $children->reject(fn (GovernanceArea $child): bool =>
                    $child->country_code === 'IR'
                    && $child->governance_type === 'country'
                    && data_get($child->metadata, 'dataset_version') === 'v2'
                )->values();
            }
        }

        $items = $children->map(function (GovernanceArea $child): array {
            $location = $child->locations->first(fn (Location $candidate): bool =>
                $candidate->status === 'active' && $candidate->location_schema_id !== null && $candidate->location_type_id !== null);
            return $location ? $this->serializeLocationBridge($child, $location) : $this->serializeArea($child);
        })->values();
        return $this->response($items);
    }

    public function path(Request $request): JsonResponse
    {
        $this->assertEnabled();
        $validated = $request->validate([
            'target_location_id' => ['nullable', 'integer'],
            'governance_area_id' => ['nullable', 'integer'],
        ]);
        $locationId = $validated['target_location_id'] ?? null;
        $governanceAreaId = $validated['governance_area_id'] ?? null;
        abort_if(($locationId === null) === ($governanceAreaId === null), 422, 'Exactly one saved project scope is required.');

        if ($governanceAreaId !== null) {
            $target = GovernanceArea::query()->official()->active()->findOrFail($governanceAreaId);
            return $this->response($this->governanceAncestors($target)->map(fn (GovernanceArea $area): array => $this->serializeArea($area)));
        }

        $target = Location::query()->with(['type', 'schema'])->where('status', 'active')
            ->whereNotNull('location_schema_id')->whereNotNull('location_type_id')->findOrFail($locationId);
        $locations = $this->locationAncestors($target);
        $country = $locations->first();
        abort_unless($country instanceof Location, 404);

        $countryArea = $country->governanceAreas()->official()->active()
            ->where('governance_type', 'country')->first();
        abort_unless($countryArea instanceof GovernanceArea, 404);

        $governance = $this->governanceAncestors($countryArea)->reject(fn (GovernanceArea $area): bool => $area->is($countryArea));
        $items = $governance->map(fn (GovernanceArea $area): array => $this->serializeArea($area))->values();
        $items->push($this->serializeLocationBridge($countryArea, $country));
        foreach ($locations->slice(1) as $location) {
            $items->push($this->serializeLocation($location));
        }
        return $this->response($items);
    }

    private function governanceAncestors(GovernanceArea $target): Collection
    {
        $path = collect();
        $cursor = $target;
        while ($cursor instanceof GovernanceArea) {
            abort_unless($cursor->area_kind === 'official' && $cursor->status === 'active', 404);
            $path->prepend($cursor);
            $cursor = $cursor->parent()->first();
        }
        return $path->values();
    }

    private function locationAncestors(Location $target): Collection
    {
        $path = collect();
        $schemaId = $target->location_schema_id;
        $cursor = $target;
        while ($cursor instanceof Location) {
            abort_unless($cursor->status === 'active' && $cursor->location_schema_id === $schemaId && $cursor->location_type_id !== null, 404);
            $cursor->loadMissing('type', 'schema');
            $path->prepend($cursor);
            $cursor = $cursor->parent()->first();
        }
        return $path->values();
    }

    private function serializeArea(GovernanceArea $area): array
    {
        return [
            'id' => $area->id,
            'identity' => 'governance:'.$area->id,
            'type_key' => $area->governance_type,
            'label' => GovernanceAreaDisplayName::for($area),
            'status' => $area->status,
            'has_children' => $area->children()->official()->active()->exists(),
            'governance_area_id' => $area->id,
            'children_url' => '/location/project-scope/options/governance/'.$area->id.'/children',
        ];
    }

    private function serializeLocationBridge(GovernanceArea $area, Location $location): array
    {
        $item = $this->serializeLocation($location);
        $locale = app()->getLocale();
        $locationLocalizedNames = $location->localized_names ?? [];
        $areaLocalizedNames = $area->localized_names ?? [];
        $language = strtolower((string) strtok(str_replace('_', '-', $locale), '-'));
        $item['label'] = $locationLocalizedNames[$locale]
            ?? $locationLocalizedNames[$language]
            ?? $areaLocalizedNames[$locale]
            ?? $areaLocalizedNames[$language]
            ?? LocationDisplayName::for($location);
        $item['governance_area_id'] = $area->id;
        return $item;
    }

    private function serializeLocation(Location $location): array
    {
        $allowedChildTypeIds = app(LocationSchemaResolver::class)->allowedChildTypes($location)->pluck('id');
        $hasChildren = $allowedChildTypeIds->isNotEmpty() && $location->children()->where('status', 'active')
            ->where('location_schema_id', $location->location_schema_id)->whereIn('location_type_id', $allowedChildTypeIds)->exists();
        return [
            'id' => $location->id,
            'identity' => 'location:'.$location->id,
            'type_key' => $location->type?->key,
            'label' => LocationDisplayName::for($location),
            'status' => $location->status,
            'has_children' => $hasChildren,
            'children_url' => '/location/options/'.$location->id.'/children',
        ];
    }

    private function response($items): JsonResponse
    {
        return response()->json(['data' => collect($items)->values(), 'proposals' => [], 'allowed_types' => []]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);
        abort_unless((bool) config('location-governance.projects_enabled'), 404);
    }
}
