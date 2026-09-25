<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\LocationProposal;
use App\Services\LocationGovernance\LocationStructureClaimService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationProposalStructureClaimController extends Controller
{
    public function __construct(private readonly LocationStructureClaimService $claims) {}

    public function store(Request $request, LocationProposal $locationProposal): JsonResponse
    {
        $validated = $request->validate(['claim_type' => ['required', 'string', 'max:64']]);
        $status = $locationProposal->status instanceof \BackedEnum
            ? $locationProposal->status->value
            : (string) $locationProposal->status;

        if (! in_array($status, LocationStructureClaimService::OPEN_STATUSES, true)) {
            abort(404);
        }

        try {
            $claim = $this->claims->findOrCreateOpenClaimForProposal(
                $locationProposal->loadMissing('type'),
                $validated['claim_type'],
                $request->user(),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'id' => $claim->id,
            'claim_type' => $claim->claim_type,
            'status' => $claim->status,
        ], $claim->wasRecentlyCreated ? 201 : 200);
    }
}
