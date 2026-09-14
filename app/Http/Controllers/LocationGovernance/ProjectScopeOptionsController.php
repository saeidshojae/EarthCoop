<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Services\LocationGovernance\LocationSchemaResolver;
use Illuminate\Http\JsonResponse;

final class ProjectScopeOptionsController extends Controller
{
    public function root(): JsonResponse
    {
        $this->assertEnabled();

        $areas = GovernanceArea::query()
            ->official()
            ->active()
            ->whereNull('parent_id')
            ->where('governance_type', 'global')
            ->orderBy('rank')
            ->orderBy('canonical_name')
            ->get();

        return $this->response($areas->map(fn (GovernanceArea $area): array => $this->serializeArea($area))->values());
    }

    public function children(GovernanceArea $governanceArea): JsonResponse
    {
        $this->assertEnabled();
        abort_unless($governanceArea->area_kind === 'official' && $governanceArea->status === 'active', 404);

        $children = $governanceArea->children()
            ->official()
            ->active()
            ->with(['locations' => fn ($query) => $query
                ->where('locations.status', 'active')
                ->with(['type', 'schema'])])
            ->orderBy('rank')
            ->orderBy('canonical_name')
            ->get();

        $items = $children->map(function (GovernanceArea $child): array {
            $location = $child->locations
                ->first(fn (Location $candidate): bool =>
                    $candidate->status === 'active'
                    && $candidate->location_schema_id !== null
                    && $candidate->location_type_id !== null
                );

            return $location
                ? $this->serializeLocationBridge($child, $location)
                : $this->serializeArea($child);
        })->values();

        return $this->response($items);
    }

    private function serializeArea(GovernanceArea $area): array
    {
        $locale = app()->getLocale();
        $localizedNames = $area->localized_names ?? [];

        return [
            'id' => $area->id,
            'identity' => 'governance:'.$area->id,
            'type_key' => $area->governance_type,
            'label' => $localizedNames[$locale] ?? $area->canonical_name,
            'status' => $area->status,
            'has_children' => $area->children()->official()->active()->exists(),
            'governance_area_id' => $area->id,
            'children_url' => '/location/project-scope/options/governance/'.$area->id.'/children',
        ];
    }

    private function serializeLocationBridge(GovernanceArea $area, Location $location): array
    {
        $locale = app()->getLocale();
        $locationLocalizedNames = $location->localized_names ?? [];
        $areaLocalizedNames = $area->localized_names ?? [];
        $allowedChildTypeIds = app(LocationSchemaResolver::class)
            ->allowedChildTypes($location)
            ->pluck('id');
        $hasChildren = $allowedChildTypeIds->isNotEmpty()
            && $location->children()
                ->where('status', 'active')
                ->where('location_schema_id', $location->location_schema_id)
                ->whereIn('location_type_id', $allowedChildTypeIds)
                ->exists();

        return [
            'id' => $location->id,
            'identity' => 'location:'.$location->id,
            'type_key' => $location->type?->key ?? $area->governance_type,
            'label' => $locationLocalizedNames[$locale]
                ?? $areaLocalizedNames[$locale]
                ?? $location->canonical_name
                ?? $area->canonical_name,
            'status' => $location->status,
            'has_children' => $hasChildren,
            'governance_area_id' => $area->id,
            'children_url' => '/location/options/'.$location->id.'/children',
        ];
    }

    private function response($items): JsonResponse
    {
        return response()->json([
            'data' => $items,
            'proposals' => [],
            'allowed_types' => [],
        ]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);
        abort_unless((bool) config('location-governance.projects_enabled'), 404);
    }
}
