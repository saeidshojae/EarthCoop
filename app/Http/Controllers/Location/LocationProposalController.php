<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationType;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'localized_names' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $parent = Location::query()->findOrFail($validated['parent_location_id']);
        $type = LocationType::query()->findOrFail($validated['location_type_id']);

        if (! $type->schemas()->where('location_schemas.id', $parent->location_schema_id)->exists()) {
            throw ValidationException::withMessages([
                'location_type_id' => 'The selected location type is not valid for the parent location schema.',
            ]);
        }

        $result = $this->proposals->propose(
            $request->user(),
            $parent,
            $type,
            [
                'canonical_name' => $validated['canonical_name'],
                'localized_names' => $validated['localized_names'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ],
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
            'evidence' => ['required', 'array', 'min:1'],
        ]);

        $this->proposals->support($locationProposal, $request->user(), $validated['evidence']);
        $locationProposal->refresh();

        return response()->json([
            'kind' => 'proposal',
            'id' => $locationProposal->id,
            'status' => $locationProposal->status->value,
            'distinct_verifiers' => $locationProposal->evidence()->distinct()->count('user_id'),
        ]);
    }
}
