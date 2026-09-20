<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationSchemaType;
use App\Models\LocationStructureClaim;
use App\Models\LocationType;
use App\Models\LocationTypeRelation;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationSchemaResolver;
use App\Services\LocationGovernance\LocationStructureClaimPolicy;
use App\Support\LocationDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationOptionsController extends Controller
{
    private const FA_TYPE_LABELS = [
        'country' => 'کشور', 'province' => 'استان / ایالت', 'county' => 'شهرستان / ناحیه', 'section' => 'بخش',
        'city' => 'شهر', 'rural_district' => 'دهستان', 'village' => 'روستا', 'urban_region' => 'منطقه',
        'neighborhood' => 'محله', 'street' => 'خیابان', 'alley' => 'کوچه', 'complex' => 'مجتمع', 'building' => 'ساختمان',
    ];

    private const OPEN_STATUSES = ['pending', 'ready_for_review', 'needs_evidence'];

    public function root(Request $request): JsonResponse
    {
        $this->assertRuntimeEnabled();
        $countryCode = strtoupper(trim((string) $request->query('country', '')));
        $locations = Location::query()->with(['type', 'schema'])->whereNull('parent_id')->where('status', 'active')
            ->whereNotNull('location_schema_id')->whereNotNull('location_type_id')
            ->whereHas('schema', fn ($query) => $query->where('status', 'active'))
            ->when($countryCode !== '', fn ($query) => $query->where('country_code', $countryCode))
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('location_schema_types')
                    ->whereColumn('location_schema_types.location_schema_id', 'locations.location_schema_id')
                    ->whereColumn('location_schema_types.location_type_id', 'locations.location_type_id')
                    ->where('location_schema_types.is_root', true);
            })->orderBy('canonical_name')->get();

        return response()->json(['data' => $locations->map(fn (Location $location) => $this->serialize($location))->values(), 'proposals' => [], 'allowed_types' => []]);
    }

    public function children(Location $location, LocationSchemaResolver $schemaResolver, LocationProposalPolicy $proposalPolicy, LocationStructureClaimPolicy $structurePolicy): JsonResponse
    {
        $this->assertRuntimeEnabled();
        if ($location->status !== 'active' || ! $location->location_schema_id || ! $location->location_type_id) abort(404);

        $allowedTypes = $schemaResolver->allowedChildTypes($location)->sortBy('canonical_name')->values();
        $allowedTypeIds = $allowedTypes->pluck('id');
        $proposableTypeIds = $allowedTypes->filter(fn (LocationType $type) => $proposalPolicy->allows($location, $type))->pluck('id');
        $children = $location->children()->with(['type', 'schema'])->where('status', 'active')
            ->where('location_schema_id', $location->location_schema_id)->whereIn('location_type_id', $allowedTypeIds)->orderBy('canonical_name')->get();
        $proposals = LocationProposal::query()->with('type')->where('parent_location_id', $location->id)->whereNull('parent_location_proposal_id')
            ->where('location_schema_id', $location->location_schema_id)->whereIn('location_type_id', $proposableTypeIds)
            ->whereIn('status', self::OPEN_STATUSES)->orderBy('canonical_name')->get();

        $claims = LocationStructureClaim::query()
            ->where('location_id', $location->id)
            ->whereIn('status', array_merge(\App\Services\LocationGovernance\LocationStructureClaimService::OPEN_STATUSES, ['approved']))
            ->get();
        $effectiveTypeCodes = $structurePolicy->effectiveResidenceChildTypeCodes($location, $claims);
        $effectiveTypes = LocationType::query()->whereIn('key', $effectiveTypeCodes)->orderBy('canonical_name')->get();
        $effectiveTypeIds = $effectiveTypes->pluck('id');
        if ($effectiveTypeIds->isNotEmpty() && $effectiveTypeCodes !== $allowedTypes->pluck('key')->values()->all()) {
            $children = $location->children()->with(['type','schema'])->where('status','active')->where('location_schema_id',$location->location_schema_id)->whereIn('location_type_id',$effectiveTypeIds)->orderBy('canonical_name')->get();
            $proposableTypeIds = $effectiveTypes->filter(fn (LocationType $type) => $proposalPolicy->allowsForResidence($location, $type, $claims->all()))->pluck('id');
            $proposals = LocationProposal::query()->with('type')->where('parent_location_id',$location->id)->whereNull('parent_location_proposal_id')->where('location_schema_id',$location->location_schema_id)->whereIn('location_type_id',$proposableTypeIds)->whereIn('status',self::OPEN_STATUSES)->orderBy('canonical_name')->get();
        }
        $structuralChoices = collect($structurePolicy->allowedClaimTypes($location))
            ->merge(($claims->pluck('claim_type')->contains(fn ($type) => in_array($type, ['single_urban_region', 'no_urban_region'], true)))
                ? ['single_neighborhood', 'no_neighborhood'] : [])
            ->unique()->values()->map(function (string $type) use ($claims): array {
                $claim = $claims->firstWhere('claim_type', $type);
                return ['claim_type' => $type, 'status' => $claim?->status ?? 'available', 'claim_id' => $claim?->id];
            });

        $officialBase = $claims->where('status', 'approved')->pluck('claim_type')
            ->intersect(['single_neighborhood', 'no_neighborhood'])->isNotEmpty();

        return response()->json([
            'data' => $children->map(fn (Location $child) => $this->serialize($child))->values(),
            'proposals' => $proposals->map(fn (LocationProposal $proposal) => $this->serializeProposal($proposal))->values(),
            'allowed_types' => $allowedTypes->map(fn (LocationType $type) => $this->serializeAllowedType($type, $proposalPolicy->allows($location, $type)))->values(),
            'effective_allowed_types' => $effectiveTypes->map(fn (LocationType $type) => $this->serializeAllowedType($type, $proposalPolicy->allowsForResidence($location, $type, $claims->all())))->values(),
            'structural_choices' => $structuralChoices,
            'official_governance_base' => $officialBase,
        ]);
    }

    public function proposalChildren(LocationProposal $locationProposal, LocationProposalPolicy $proposalPolicy): JsonResponse
    {
        $this->assertRuntimeEnabled();
        $status = $locationProposal->status instanceof LocationProposalStatus ? $locationProposal->status->value : (string) $locationProposal->status;
        if (! in_array($status, self::OPEN_STATUSES, true) || ! $locationProposal->location_schema_id || ! $locationProposal->location_type_id) abort(404);

        $allowedTypeIds = LocationTypeRelation::query()
            ->where('location_schema_id', $locationProposal->location_schema_id)
            ->where('parent_type_id', $locationProposal->location_type_id)
            ->pluck('child_type_id');
        $allowedTypes = LocationType::query()->whereIn('id', $allowedTypeIds)->orderBy('canonical_name')->get();
        $proposals = LocationProposal::query()->with('type')->whereNull('parent_location_id')
            ->where('parent_location_proposal_id', $locationProposal->id)
            ->whereIn('location_type_id', $allowedTypeIds)->whereIn('status', self::OPEN_STATUSES)->orderBy('canonical_name')->get();

        return $this->payload(collect(), $proposals, $allowedTypes, fn (LocationType $type) => $proposalPolicy->allowsProposalParent($locationProposal, $type));
    }

    private function payload($children, $proposals, $allowedTypes, callable $proposalAllowed): JsonResponse
    {
        return response()->json([
            'data' => $children->map(fn (Location $child) => $this->serialize($child))->values(),
            'proposals' => $proposals->map(fn (LocationProposal $proposal) => $this->serializeProposal($proposal))->values(),
            'allowed_types' => $allowedTypes->map(fn (LocationType $type) => $this->serializeAllowedType($type, $proposalAllowed($type)))->values(),
        ]);
    }

    private function serialize(Location $location): array
    {
        $label = LocationDisplayName::for($location);
        $schemaType = LocationSchemaType::query()->where('location_schema_id', $location->location_schema_id)->where('location_type_id', $location->location_type_id)->first();
        $allowedChildTypeIds = app(LocationSchemaResolver::class)->allowedChildTypes($location)->pluck('id');
        $hasChildren = $allowedChildTypeIds->isNotEmpty() && $location->children()->where('status', 'active')
            ->where('location_schema_id', $location->location_schema_id)->whereIn('location_type_id', $allowedChildTypeIds)->exists();

        return ['id' => $location->id, 'identity' => 'location:'.$location->id, 'type_key' => $location->type?->key, 'label' => $label,
            'is_residence_endpoint' => (bool) ($schemaType?->is_residence_endpoint ?? false), 'has_children' => $hasChildren, 'status' => $location->status];
    }

    private function serializeProposal(LocationProposal $proposal): array
    {
        $status = $proposal->status instanceof LocationProposalStatus ? $proposal->status->value : (string) $proposal->status;
        return ['id' => $proposal->id, 'identity' => 'proposal:'.$proposal->id, 'type_key' => $proposal->type?->key,
            'label' => LocationDisplayName::for($proposal), 'status' => $status, 'selectable' => true];
    }

    private function serializeAllowedType(LocationType $type, bool $proposalAllowed): array
    {
        $label = app()->getLocale() === 'fa' ? (self::FA_TYPE_LABELS[$type->key] ?? $type->canonical_name) : $type->canonical_name;
        return ['id' => $type->id, 'key' => $type->key, 'label' => $label, 'proposal_allowed' => $proposalAllowed];
    }

    private function assertRuntimeEnabled(): void
    {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);
    }
}
