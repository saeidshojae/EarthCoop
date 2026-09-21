<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationStructureClaimController extends Controller
{
    public function __construct(private readonly LocationStructureClaimService $claims) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'claim_type' => ['required', 'string', 'max:64'],
        ]);

        $location = Location::query()->findOrFail($validated['location_id']);
        abort_unless($location->status === 'active', 422, 'The selected location is not active.');

        try {
            $claim = $this->claims->findOrCreateOpenClaim($location, $validated['claim_type'], $request->user());
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'id' => $claim->id,
            'location_id' => $claim->location_id,
            'claim_type' => $claim->claim_type,
            'status' => $claim->status,
        ]);
    }
}
