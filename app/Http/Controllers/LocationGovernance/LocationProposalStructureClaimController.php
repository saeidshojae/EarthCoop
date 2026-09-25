<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Services\LocationGovernance\LocationStructureClaimService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationProposalStructureClaimController extends Controller
{
    public function store(Request $request, LocationProposal $locationProposal): JsonResponse
    {
        $validated = $request->validate(['claim_type' => ['required', 'string', 'max:64']]);
        $status = $locationProposal->status instanceof \BackedEnum ? $locationProposal->status->value : (string) $locationProposal->status;
        if (! in_array($status, ['pending', 'ready_for_review', 'needs_evidence'], true)) abort(404);

        $allowed = match ($locationProposal->type?->key) {
            'city' => ['single_urban_region', 'no_urban_region'],
            'urban_region', 'village' => ['single_neighborhood', 'no_neighborhood'],
            default => [],
        };
        $type = $validated['claim_type'];
        if (! in_array($type, $allowed, true)) {
            return response()->json(['message' => 'این وضعیت ساختاری برای نوع مکان انتخاب‌شده مجاز نیست.'], 422);
        }
        $opposite = match ($type) {
            'single_urban_region' => 'no_urban_region',
            'no_urban_region' => 'single_urban_region',
            'single_neighborhood' => 'no_neighborhood',
            'no_neighborhood' => 'single_neighborhood',
        };
        $active = [...LocationStructureClaimService::OPEN_STATUSES, 'approved'];
        if (LocationStructureClaim::query()->where('location_proposal_id', $locationProposal->id)->where('claim_type', $opposite)->whereIn('status', $active)->exists()) {
            return response()->json(['message' => 'برای این سطح، وضعیت ساختاری متناقض قبلاً ثبت شده است.'], 422);
        }
        $claim = LocationStructureClaim::query()
            ->where('location_proposal_id', $locationProposal->id)
            ->where('claim_type', $type)->whereIn('status', $active)->first();
        if ($claim === null) {
            $claim = LocationStructureClaim::query()->create([
                'location_id' => null, 'location_proposal_id' => $locationProposal->id,
                'claim_type' => $type, 'status' => 'pending', 'proposer_user_id' => $request->user()?->id,
                'metadata' => ['source' => 'pending_location_structure'], 'audit_log' => [],
            ]);
        }
        return response()->json(['id' => $claim->id, 'claim_type' => $claim->claim_type, 'status' => $claim->status], $claim->wasRecentlyCreated ? 201 : 200);
    }
}
