<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Illuminate\Validation\ValidationException;
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

        $hasExternalParent = filled($input['parent_external_id'] ?? null);
        $hasLocationParent = isset($input['parent_location_id']);
        if ($hasExternalParent === $hasLocationParent) {
            throw ValidationException::withMessages([
                'parent_external_id' => 'Exactly one source parent or canonical parent location is required.',
            ]);
        }

        $parentExternalId = $input['parent_external_id'] ?? null;
        if ($hasLocationParent) {
            $identity = LocationExternalId::query()
                ->where('location_id', (int) $input['parent_location_id'])
                ->where('source', ReferenceGeographyImporter::SOURCE)
                ->where('dataset_version', 'v2')
                ->first();
            if ($identity === null || ! preg_match('/^IR-1404-[1-9][0-9]*$/D', (string) $identity->external_id)) {
                throw ValidationException::withMessages([
                    'parent_location_id' => 'The selected location has no Iran 1404 reference identity.',
                ]);
            }
            $parentExternalId = $identity->external_id;
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
}
