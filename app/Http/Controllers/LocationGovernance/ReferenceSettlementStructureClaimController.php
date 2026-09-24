<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\ReferenceSettlement;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ReferenceSettlementStructureClaimController extends Controller
{
    public function store(
        Request $request,
        string $externalId,
        LocationStructureClaimService $claims,
    ): JsonResponse {
        abort_unless((bool) config('iran_settlement_catalog.enabled', false)
            && (bool) config('iran_settlement_catalog.claims_enabled', false), 404);

        if (! Schema::hasColumn('location_structure_claims', 'reference_settlement_id')) {
            return response()->json([
                'message' => 'ساختار جدید آبادی‌ها هنوز روی این محیط به‌روزرسانی نشده است. ابتدا migrationهای مکان را اجرا کنید.',
                'code' => 'reference_settlement_structure_migration_required',
            ], 503);
        }

        abort_unless((bool) preg_match('/^IR-1404-[1-9][0-9]*$/D', $externalId), 404);

        $validated = $request->validate([
            'claim_type' => ['required', 'in:no_neighborhood'],
        ]);

        $settlement = ReferenceSettlement::query()
            ->where('source', 'IranCountryDivisions/geo_1404')
            ->where('dataset_version', 'v2')
            ->where('external_id', $externalId)
            ->firstOrFail();

        try {
            $claim = $claims->findOrCreateOpenReferenceSettlementClaim(
                $settlement,
                (string) $validated['claim_type'],
                $request->user(),
            );
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages([
                'claim_type' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'id' => $claim->id,
            'claim_type' => $claim->claim_type,
            'status' => $claim->status,
            'reference_settlement_id' => $settlement->id,
        ], $claim->wasRecentlyCreated ? 201 : 200);
    }
}
