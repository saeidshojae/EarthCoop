<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\ReferenceSettlement;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\LocationType;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Support\LocationDisplayName;
use App\Models\LocationExternalId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IranSettlementCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless((bool) config('iran_settlement_catalog.enabled', false), 404);

        $input = $request->validate([
            'parent_external_id' => ['nullable', 'string', 'regex:/^IR-1404-[1-9][0-9]*$/D'],
            'parent_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'q' => ['sometimes', 'string', 'min:2', 'max:60', 'regex:/^[\\p{L}\\p{N} \\x{200c}\\-]+$/u'],
            'after_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $parentExternalId = $input['parent_external_id'] ?? null;
        $parentLocationId = $input['parent_location_id'] ?? null;
        if (($parentExternalId === null) === ($parentLocationId === null)) {
            return response()->json(['message' => 'Exactly one parent identity is required.'], 422);
        }

        if ($parentLocationId !== null) {
            $directV2ExternalId = LocationExternalId::query()
                ->where('location_id', (int) $parentLocationId)
                ->where('source', 'earthcoop-reference')
                ->where('dataset_version', 'v2')
                ->value('external_id');

            if (is_string($directV2ExternalId) && preg_match('/^IR-1404-[1-9][0-9]*$/D', $directV2ExternalId)) {
                $parentExternalId = $directV2ExternalId;
            } else {
                $v1ExternalId = LocationExternalId::query()
                    ->where('location_id', (int) $parentLocationId)
                    ->where('source', config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
                    ->where('dataset_version', config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1'))
                    ->value('external_id');
                $mapping = $v1ExternalId ? config('iran_v1_v2_crosswalk.mappings.'.$v1ExternalId) : null;
                if (! is_array($mapping) || ($mapping['status'] ?? null) !== 'verified_identity') {
                    return response()->json([
                        'message' => 'این والد هنوز شناسه قطعی در منبع ۱۴۰۴ ندارد.',
                        'data' => [],
                    ], 422);
                }
                $parentExternalId = (string) $mapping['v2'];
            }
        }

        $query = ReferenceSettlement::query()
            ->where('source', 'IranCountryDivisions/geo_1404')
            ->where('dataset_version', 'v2')
            ->where('parent_external_id', $parentExternalId)
            ->when(isset($input['q']), fn ($query) => $query->where('search_name', 'like', trim($input['q']).'%'))
            ->when(isset($input['after_id']), fn ($query) => $query->where('id', '>', (int) $input['after_id']))
            ->orderBy('id')
            ->limit(21);
        $results = $query->get();
        $page = $results->take(20);
        $items = $page->map(fn (ReferenceSettlement $settlement): array => [
            'id' => $settlement->id,
            'external_id' => $settlement->external_id,
            'parent_external_id' => $settlement->parent_external_id,
            'name_fa' => $settlement->name_fa,
            'type' => 'settlement',
            'classification' => $settlement->classification,
            'residential_eligibility' => $settlement->residential_eligibility,
            'residence_endpoint_allowed' => false,
            'governance_authorized' => false,
            'requires_residence_review' => true,
        ])->values()->all();
        return response()->json([
            'data' => $items,
            'next_after_id' => $results->count() > 20 ? $page->last()?->id : null,
        ]);
    }

    public function children(Request $request, string $externalId, LocationProposalPolicy $proposalPolicy): JsonResponse
    {
        abort_unless((bool) config('iran_settlement_catalog.enabled', false)
            && (bool) config('iran_settlement_catalog.claims_enabled', false), 404);
        abort_unless((bool) preg_match('/^IR-1404-[1-9][0-9]*$/D', $externalId), 404);

        $settlement = ReferenceSettlement::query()
            ->where('source', 'IranCountryDivisions/geo_1404')
            ->where('dataset_version', 'v2')
            ->where('external_id', $externalId)->firstOrFail();
        $open = [LocationProposalStatus::Pending->value, LocationProposalStatus::ReadyForReview->value, LocationProposalStatus::NeedsEvidence->value];
        $noNeighborhoodClaim = LocationStructureClaim::query()
            ->where('reference_settlement_id', $settlement->id)
            ->where('claim_type', 'no_neighborhood')
            ->whereIn('status', [...LocationStructureClaimService::OPEN_STATUSES, 'approved'])
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        $requestedClaimIds = collect($request->query('location_structure_claim_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();
        $noNeighborhoodEffective = $noNeighborhoodClaim !== null
            && ($noNeighborhoodClaim->status === 'approved'
                || $requestedClaimIds->contains((int) $noNeighborhoodClaim->id));

        $targetType = LocationType::query()
            ->where('key', $noNeighborhoodEffective ? 'street' : 'neighborhood')
            ->first();
        $effectiveClaims = $noNeighborhoodEffective ? [$noNeighborhoodClaim] : [];
        $allowed = $targetType !== null
            && $proposalPolicy->allowsReferenceSettlementParentForResidence($settlement, $targetType, $effectiveClaims);

        $proposals = $allowed ? LocationProposal::query()->with('type')
            ->where('parent_reference_settlement_id', $settlement->id)
            ->where('location_type_id', $targetType->id)
            ->whereIn('status', $open)->orderBy('id')->get() : collect();

        return response()->json([
            'reference_settlement' => ['id' => $settlement->id, 'external_id' => $settlement->external_id, 'name_fa' => $settlement->name_fa],
            'proposals' => $proposals->map(fn (LocationProposal $proposal): array => [
                'id' => $proposal->id,
                'identity' => 'proposal:'.$proposal->id,
                'type_key' => $proposal->type?->key,
                'label' => LocationDisplayName::for($proposal),
                'status' => $proposal->status instanceof LocationProposalStatus ? $proposal->status->value : (string) $proposal->status,
                'selectable' => true,
                'children_url' => '/location/proposals/'.$proposal->id.'/children',
            ])->values()->all(),
            'allowed_types' => $allowed ? [[
                'id' => $targetType->id,
                'key' => $targetType->key,
                'label' => $targetType->key === 'street' ? 'خیابان' : 'محله',
                'proposal_allowed' => true,
            ]] : [],
            'structural_choices' => [[
                'claim_type' => 'no_neighborhood',
                'status' => $noNeighborhoodClaim?->status ?? 'available',
                'claim_id' => $noNeighborhoodClaim?->id,
                'selected' => $noNeighborhoodEffective,
            ]],
            'registration_endpoint_allowed' => $noNeighborhoodEffective,
            'governance_authorized' => false,
        ]);
    }
}
