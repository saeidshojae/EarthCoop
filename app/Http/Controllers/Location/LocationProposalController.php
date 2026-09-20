<?php

namespace App\Http\Controllers\Location;

use DomainException;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\LocationType;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationProposalController extends Controller
{
    public function __construct(private readonly LocationProposalService $proposals) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'parent_location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
            'location_type_id' => ['required', 'integer', 'exists:location_types,id'],
            'canonical_name' => ['required', 'string', 'max:255'],
            'localized_names' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'location_structure_claim_ids' => ['sometimes', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
        ]);

        $parentLocationId = $validated['parent_location_id'] ?? null;
        $parentProposalId = $validated['parent_location_proposal_id'] ?? null;
        if (($parentLocationId === null) === ($parentProposalId === null)) {
            throw ValidationException::withMessages(['parent_location_id' => 'Exactly one location or pending proposal parent must be selected.']);
        }

        $type = LocationType::query()->findOrFail($validated['location_type_id']);
        $requestedStructuralClaimIds = collect($validated['location_structure_claim_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        $structuralClaims = LocationStructureClaim::query()
            ->whereIn('id', $requestedStructuralClaimIds)
            ->get();

        if ($structuralClaims->count() !== $requestedStructuralClaimIds->count()) {
            throw ValidationException::withMessages([
                'location_structure_claim_ids' => 'One or more structural claims are unavailable.',
            ]);
        }
        $data = [
            'canonical_name' => $validated['canonical_name'],
            'localized_names' => $validated['localized_names'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ];

        if ($parentLocationId !== null) {
            $parent = Location::query()->findOrFail($parentLocationId);
            if ($parent->status !== 'active') {
                throw ValidationException::withMessages(['parent_location_id' => 'The selected parent location is not active.']);
            }
            if ($structuralClaims->contains(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->location_id !== (int) $parent->id
                || (int) $claim->proposer_user_id !== (int) $request->user()->id
            )) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'Structural claims must belong to this location and the current user.',
                ]);
            }

            try {
            $result = $this->proposals->propose($request->user(), $parent, $type, $data, $structuralClaims->all());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        } else {
            $parent = LocationProposal::query()->findOrFail($parentProposalId);
            $result = $this->proposals->proposeUnderProposal($request->user(), $parent, $type, $data);
        }

        if ($result instanceof Location) {
            return response()->json(['kind' => 'location', 'id' => $result->id, 'canonical_name' => $result->canonical_name]);
        }

        return response()->json([
            'kind' => 'proposal', 'id' => $result->id, 'canonical_name' => $result->canonical_name,
            'status' => $result->status->value,
        ], $result->wasRecentlyCreated ? 201 : 200);
    }

    public function support(Request $request, LocationProposal $locationProposal): JsonResponse
    {
        $validated = $request->validate(['evidence' => ['required', 'array', 'min:1']]);
        $this->proposals->support($locationProposal, $request->user(), $validated['evidence']);
        $locationProposal->refresh();
        return response()->json([
            'kind' => 'proposal', 'id' => $locationProposal->id, 'status' => $locationProposal->status->value,
            'distinct_verifiers' => $locationProposal->evidence()->distinct()->count('user_id'),
        ]);
    }
}
