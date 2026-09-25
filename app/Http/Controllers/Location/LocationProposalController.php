<?php

namespace App\Http\Controllers\Location;

use DomainException;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\ReferenceSettlement;
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
            'parent_reference_settlement_id' => ['nullable', 'integer', 'exists:reference_settlements,id'],
            'location_type_id' => ['required', 'integer', 'exists:location_types,id'],
            'canonical_name' => ['required', 'string', 'max:255'],
            'localized_names' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'location_structure_claim_ids' => ['sometimes', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
        ]);

        $parentLocationId = $validated['parent_location_id'] ?? null;
        $parentProposalId = $validated['parent_location_proposal_id'] ?? null;
        $parentReferenceSettlementId = $validated['parent_reference_settlement_id'] ?? null;
        $parentCount = collect([$parentLocationId, $parentProposalId, $parentReferenceSettlementId])
            ->filter(fn ($value) => $value !== null)->count();
        if ($parentCount !== 1) {
            throw ValidationException::withMessages(['parent_location_id' => 'Exactly one canonical, proposal, or reference-settlement parent must be selected.']);
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
            )) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'Structural claims must belong to the selected parent location.',
                ]);
            }

            try {
            $result = $this->proposals->propose($request->user(), $parent, $type, $data, $structuralClaims->all());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        } elseif ($parentProposalId !== null) {
            $parent = LocationProposal::query()->findOrFail($parentProposalId);
            if ($structuralClaims->contains(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->location_proposal_id !== (int) $parent->id
            )) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'Structural claims must belong to the selected pending parent proposal.',
                ]);
            }

            try {
                $result = $this->proposals->proposeUnderProposal(
                    $request->user(),
                    $parent,
                    $type,
                    $data,
                    $structuralClaims->all(),
                );
            } catch (DomainException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        } else {
            $parent = ReferenceSettlement::query()->findOrFail($parentReferenceSettlementId);
            if ($structuralClaims->contains(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->reference_settlement_id !== (int) $parent->id
            )) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'Structural claims must belong to the selected reference settlement.',
                ]);
            }

            try {
                $result = $this->proposals->proposeUnderReferenceSettlement(
                    $request->user(),
                    $parent,
                    $type,
                    $data,
                    $structuralClaims->all(),
                );
            } catch (DomainException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        if ($result instanceof Location) {
            return response()->json(['kind' => 'location', 'id' => $result->id, 'canonical_name' => $result->canonical_name]);
        }

        return response()->json([
            'kind' => 'proposal', 'id' => $result->id, 'canonical_name' => $result->canonical_name,
            'status' => $result->status->value,
            'type_key' => $result->type?->key,
            'children_url' => '/location/proposals/'.$result->id.'/children',
        ], $result->wasRecentlyCreated ? 201 : 200);
    }

}
