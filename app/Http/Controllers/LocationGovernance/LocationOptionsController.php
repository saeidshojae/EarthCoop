<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Services\LocationGovernance\Api\CanonicalLocationOptionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationOptionsController extends Controller
{
    public function __construct(private readonly CanonicalLocationOptionsQuery $query)
    {
    }

    public function root(Request $request): JsonResponse
    {
        return response()->json($this->query->root($request->query('country')));
    }

    public function children(Request $request, Location $location): JsonResponse
    {
        return response()->json($this->query->children($location, $this->claimIds($request)));
    }

    public function proposalChildren(Request $request, LocationProposal $locationProposal): JsonResponse
    {
        return response()->json($this->query->proposalChildren($locationProposal, $this->claimIds($request)));
    }

    private function claimIds(Request $request): array
    {
        $raw = $request->query('location_structure_claim_ids', []);
        return is_array($raw) ? $raw : [$raw];
    }
}
