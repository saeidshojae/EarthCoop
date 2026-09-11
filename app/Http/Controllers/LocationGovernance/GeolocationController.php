<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationGovernance\GeolocationMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeolocationController extends Controller
{
    public function match(Request $request, GeolocationMatchService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'locale' => ['sometimes', 'string', 'max:20'],
            'manual_location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $manualSelection = isset($validated['manual_location_id'])
            ? Location::query()->findOrFail((int) $validated['manual_location_id'])
            : null;

        $match = $service->match(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (string) ($validated['locale'] ?? app()->getLocale()),
            $manualSelection,
        );

        return response()->json([
            'status' => $match->status,
            'suggested_location_id' => $match->suggestedLocationId,
            'manual_location_id' => $match->manualLocationId,
            'confidence' => $match->confidence,
            'gps_consistent' => $match->gpsConsistent,
            'manual_selection_available' => $match->manualSelectionAvailable,
            'provider' => $match->provider,
        ]);
    }
}
