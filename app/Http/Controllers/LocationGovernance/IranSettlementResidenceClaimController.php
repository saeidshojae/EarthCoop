<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IranSettlementResidenceClaimController extends Controller
{
    /**
     * A geographic assertion, not a registered residence or group membership.
     * This isolated intake is OFF by default and must never call ResidenceService.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless((bool) config('iran_settlement_catalog.enabled', false)
            && (bool) config('iran_settlement_catalog.claims_enabled', false), 404);

        $input = $request->validate([
            'external_id' => ['required', 'string', 'regex:/^IR-1404-[1-9][0-9]*$/D'],
        ]);

        $claim = DB::transaction(function () use ($request, $input): ReferenceSettlementResidenceClaim {
            $settlement = ReferenceSettlement::query()
                ->where('source', 'IranCountryDivisions/geo_1404')
                ->where('dataset_version', 'v2')
                ->where('external_id', $input['external_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($settlement->classification !== 'unverified_settlement'
                || $settlement->residential_eligibility !== 'unverified'
                || $settlement->governance_authorized
                || $settlement->operational_promotion_allowed) {
                throw ValidationException::withMessages([
                    'external_id' => 'این آبادی در وضعیت درخواست سکونتِ در انتظار بررسی نیست.',
                ]);
            }

            return ReferenceSettlementResidenceClaim::query()->firstOrCreate(
                ['reference_settlement_id' => $settlement->id, 'user_id' => $request->user()->id],
                ['status' => 'pending', 'submitted_at' => now()],
            );
        });

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
