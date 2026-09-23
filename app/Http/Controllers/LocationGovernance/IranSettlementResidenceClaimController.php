<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\LocationGovernance\ReferenceSettlementResidenceClaimService;
use Illuminate\Validation\ValidationException;

final class IranSettlementResidenceClaimController extends Controller
{
    /**
     * Private claimant-facing status. No other user's evidence or identity is exposed.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless((bool) config('iran_settlement_catalog.enabled', false)
            && (bool) config('iran_settlement_catalog.claims_enabled', false), 404);

        $claims = ReferenceSettlementResidenceClaim::query()
            ->where('user_id', $request->user()->id)
            ->with('settlement:id,external_id,name_fa')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ReferenceSettlementResidenceClaim $claim): array => [
                'id' => $claim->id,
                'external_id' => $claim->settlement?->external_id,
                'name_fa' => $claim->settlement?->name_fa,
                'status' => $claim->status,
                'residence_confirmed' => false,
                'official_groups_activated' => false,
                'submitted_at' => $claim->submitted_at?->toIso8601String(),
            ])->values();

        return response()->json(['data' => $claims]);
    }

    /**
     * A geographic assertion, not a registered residence or group membership.
     * This isolated intake is OFF by default and must never call ResidenceService.
     */
    public function store(Request $request, ReferenceSettlementResidenceClaimService $service): JsonResponse
    {
        abort_unless((bool) config('iran_settlement_catalog.enabled', false)
            && (bool) config('iran_settlement_catalog.claims_enabled', false), 404);

        $input = $request->validate([
            'external_id' => ['required', 'string', 'regex:/^IR-1404-[1-9][0-9]*$/D'],
        ]);

        $settlement = ReferenceSettlement::query()
            ->where('source', 'IranCountryDivisions/geo_1404')
            ->where('dataset_version', 'v2')
            ->where('external_id', $input['external_id'])
            ->firstOrFail();

        $claim = $service->claim($request->user(), $settlement);

        return response()->json([
            'claim_id' => $claim->id,
            'external_id' => $input['external_id'],
            'status' => $claim->status,
            'review_required' => true,
            'residence_confirmed' => false,
            'official_groups_activated' => false,
            'message' => 'درخواست بررسی سکونت در آبادی موجود ثبت شده است؛ این درخواست به‌معنای تأیید سکونت نیست.',
        ], $claim->wasRecentlyCreated ? 201 : 200);
    }
}
