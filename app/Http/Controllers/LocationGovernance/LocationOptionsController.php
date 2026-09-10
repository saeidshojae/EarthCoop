<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationSchemaType;
use App\Services\LocationGovernance\LocationSchemaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationOptionsController extends Controller
{
    public function root(Request $request): JsonResponse
    {
        $this->assertRuntimeEnabled();

        $countryCode = strtoupper(trim((string) $request->query('country', '')));
        if ($countryCode === '') {
            return response()->json(['data' => []]);
        }

        $locations = Location::query()
            ->with(['type', 'schema'])
            ->whereNull('parent_id')
            ->where('country_code', $countryCode)
            ->where('status', 'active')
            ->whereNotNull('location_schema_id')
            ->whereNotNull('location_type_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('location_schema_types')
                    ->whereColumn('location_schema_types.location_schema_id', 'locations.location_schema_id')
                    ->whereColumn('location_schema_types.location_type_id', 'locations.location_type_id')
                    ->where('location_schema_types.is_root', true);
            })
            ->orderBy('canonical_name')
            ->get();

        return response()->json([
            'data' => $locations->map(fn (Location $location): array => $this->serialize($location))->values(),
        ]);
    }

    public function children(Location $location, LocationSchemaResolver $schemaResolver): JsonResponse
    {
        $this->assertRuntimeEnabled();

        if ($location->status !== 'active' || ! $location->location_schema_id || ! $location->location_type_id) {
            abort(404);
        }

        $allowedTypeIds = $schemaResolver->allowedChildTypes($location)->pluck('id');

        $children = $location->children()
            ->with(['type', 'schema'])
            ->where('status', 'active')
            ->where('location_schema_id', $location->location_schema_id)
            ->whereIn('location_type_id', $allowedTypeIds)
            ->orderBy('canonical_name')
            ->get();

        return response()->json([
            'data' => $children->map(fn (Location $child): array => $this->serialize($child))->values(),
        ]);
    }

    private function serialize(Location $location): array
    {
        $locale = app()->getLocale();
        $localizedNames = $location->localized_names ?? [];
        $label = $localizedNames[$locale] ?? $location->canonical_name ?? $location->name;

        $schemaType = LocationSchemaType::query()
            ->where('location_schema_id', $location->location_schema_id)
            ->where('location_type_id', $location->location_type_id)
            ->first();

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
            'type_key' => $location->type?->key,
            'label' => $label,
            'is_residence_endpoint' => (bool) ($schemaType?->is_residence_endpoint ?? false),
            'has_children' => $hasChildren,
            'status' => $location->status,
        ];
    }

    private function assertRuntimeEnabled(): void
    {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);
    }
}
