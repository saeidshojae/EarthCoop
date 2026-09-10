<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationType;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationProposalController extends Controller
{
    public function __construct(private readonly LocationProposalService $proposals)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_location_id' => ['required', 'integer', 'exists:locations,id'],
            'location_type_id' => ['required', 'integer', 'exists:location_types,id'],
            'canonical_name' => ['required', 'string', 'max:255'],
            'localized_names' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $parent = Location::query()->findOrFail($validated['parent_location_id']);
        $type = LocationType::query()->findOrFail($validated['location_type_id']);

        $result = $this->proposals->propose(
            $request->user(),
            $parent,
            $type,
            $validated['canonical_name'],
            $validated['localized_names'] ?? [],
            $validated['metadata'] ?? [],
        );

        if ($result instanceof Location) {
            return response()->json([
                'kind' => 'location',
                'id' => $result->id,
                'canonical_name' => $result->canonical_name,
            ]);
        }

        return response()->json([
            'kind' => 'proposal',
            'id' => $result->id,
            'canonical_name' => $result->canonical_name,
            'status' => $result->status->value,
        ], $result->wasRecentlyCreated ? 201 : 200);
    }

    public function support(Request $request, LocationProposal $locationProposal): JsonResponse
    {
        $validated = $request->validate([
            'evidence' => ['required', 'array'],
        ]);

        $this->proposals->support($locationProposal, $request->user(), $validated['evidence']);
        $locationProposal->refresh();

        return response()->json([
            'kind' => 'proposal',
            'id' => $locationProposal->id,
            'status' => $locationProposal->status->value,
            'distinct_verifiers' => $locationProposal->evidence()
                ->distinct('user_id')
                ->count('user_id'),
        ]);
    }
}
