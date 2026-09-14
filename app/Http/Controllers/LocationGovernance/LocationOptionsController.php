<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationSchemaType;
use App\Models\LocationType;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationSchemaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationOptionsController extends Controller
{
    public function root(Request $request): JsonResponse
    {
        $this->assertRuntimeEnabled();

        $countryCode = strtoupper(trim((string) $request->query('country', '')));

        $locations = Location::query()
            ->with(['type', 'schema'])
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->whereNotNull('location_schema_id')
            ->whereNotNull('location_type_id')
            ->whereHas('schema', fn ($query) => $query->where('status', 'active'))
            ->when($countryCode !== '', fn ($query) => $query->where('country_code', $countryCode))
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('location_schema_types')
                    ->whereColumn('location_schema_types.location_schema_id', 'locations.location_schema_id')
                    ->whereColumn('location_schema_types.location_type_id', 'locations.location_type_id')
                    ->where('location_schema_types.is_root', true);
            })
            ->orderBy('canonical_name')
            ->get();

        return response()->json([
            'data' => $locations->map(fn (Location $location): array => $this->serialize($location))->values(),
            'proposals' => [],
            'allowed_types' => [],
        ]);
    }

    public function children(
        Location $location,
        LocationSchemaResolver $schemaResolver,
        LocationProposalPolicy $proposalPolicy,
    ): JsonResponse {
        $this->assertRuntimeEnabled();

        if ($location->status !== 'active' || ! $location->location_schema_id || ! $location->location_type_id) {
            abort(404);
        }

        $allowedTypes = $schemaResolver->allowedChildTypes($location)
            ->sortBy('canonical_name')
            ->values();
        $allowedTypeIds = $allowedTypes->pluck('id');
        $proposableTypeIds = $allowedTypes
            ->filter(fn (LocationType $type): bool => $proposalPolicy->allows($location, $type))
            ->pluck('id');

        $children = $location->children()
            ->with(['type', 'schema'])
            ->where('status', 'active')
            ->where('location_schema_id', $location->location_schema_id)
            ->whereIn('location_type_id', $allowedTypeIds)
            ->orderBy('canonical_name')
            ->get();

        $proposals = LocationProposal::query()
            ->with('type')
            ->where('parent_location_id', $location->id)
            ->where('location_schema_id', $location->location_schema_id)
            ->whereIn('location_type_id', $proposableTypeIds)
            ->whereIn('status', [
                LocationProposalStatus::Pending->value,
                LocationProposalStatus::ReadyForReview->value,
                LocationProposalStatus::NeedsEvidence->value,
            ])
            ->orderBy('canonical_name')
            ->get();

        return response()->json([
            'data' => $children->map(fn (Location $child): array => $this->serialize($child))->values(),
            'proposals' => $proposals->map(fn (LocationProposal $proposal): array => $this->serializeProposal($proposal))->values(),
            'allowed_types' => $allowedTypes->map(
                fn (LocationType $type): array => $this->serializeAllowedType(
                    $type,
                    $proposalPolicy->allows($location, $type),
                )
            )->values(),
        ]);
    }

    private function serialize(Location $location): array
    {
        $locale = app()->getLocale();
        $localizedNames = $location->localized_names ?? [];
        $label = $localizedNames[$locale] ?? $location->canonical_name ?? $location->name;

        $schemaType = LocationSchemaType::query()
            ->where('location_schema_id', $location->location_schema_id)
            ->where('location_type_id', $location->location_type_id)
            ->first();

        $allowedChildTypeIds = app(LocationSchemaResolver::class)
            ->allowedChildTypes($location)
            ->pluck('id');

        $hasChildren = $allowedChildTypeIds->isNotEmpty()
            && $location->children()
                ->where('status', 'active')
                ->where('location_schema_id', $location->location_schema_id)
                ->whereIn('location_type_id', $allowedChildTypeIds)
                ->exists();

        return [
            'id' => $location->id,
            'identity' => 'location:'.$location->id,
            'type_key' => $location->type?->key,
            'label' => $label,
            'is_residence_endpoint' => (bool) ($schemaType?->is_residence_endpoint ?? false),
            'has_children' => $hasChildren,
            'status' => $location->status,
        ];
    }

    private function serializeProposal(LocationProposal $proposal): array
    {
        $locale = app()->getLocale();
        $localizedNames = $proposal->localized_names ?? [];
        $label = $localizedNames[$locale] ?? $proposal->canonical_name;
        $status = $proposal->status instanceof LocationProposalStatus
            ? $proposal->status->value
            : (string) $proposal->status;

        return [
            'id' => $proposal->id,
            'identity' => 'proposal:'.$proposal->id,
            'type_key' => $proposal->type?->key,
            'label' => $label,
            'status' => $status,
            'selectable' => true,
        ];
    }

    private function serializeAllowedType(LocationType $type, bool $proposalAllowed): array
    {
        return [
            'id' => $type->id,
            'key' => $type->key,
            'label' => $type->canonical_name,
            'proposal_allowed' => $proposalAllowed,
        ];
    }

    private function assertRuntimeEnabled(): void
    {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);
    }
}
