<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Services\LocationGovernance\Api\CanonicalLocationOptionsQuery;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LocationGovernanceController extends Controller
{
    public function __construct(
        private readonly CanonicalLocationOptionsQuery $options,
        private readonly ResidenceService $residenceService,
        private readonly LocationTreeResolver $locationTreeResolver,
    ) {
    }

    public function root(Request $request): JsonResponse
    {
        return response()->json($this->options->root($request->query('country')));
    }

    public function children(Request $request, Location $location): JsonResponse
    {
        return response()->json($this->options->children($location, $this->claimIds($request)));
    }

    public function proposalChildren(Request $request, LocationProposal $locationProposal): JsonResponse
    {
        return response()->json($this->options->proposalChildren($locationProposal, $this->claimIds($request)));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->summary($request));
    }

    public function updateResidence(Request $request): JsonResponse
    {
        abort_unless((bool) config('location-governance.registration_enabled'), 404);

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'location_structure_claim_ids' => ['nullable', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
        ]);

        $user = $request->user();
        $location = Location::query()->findOrFail($validated['location_id']);
        $claims = LocationStructureClaim::query()->whereIn('id', $validated['location_structure_claim_ids'] ?? [])->get()->all();

        if ($location->status !== 'active' || ! $this->locationTreeResolver->residenceSelectionEndpointAllowed($location, $claims)) {
            throw ValidationException::withMessages([
                'location_id' => 'لطفاً یک محل سکونت معتبر و قابل انتخاب را مشخص کنید.',
            ]);
        }

        $current = $this->residenceService->currentPrimaryResidence($user);
        if ($current === null) {
            $this->residenceService->setInitialPrimaryResidence($user, $location, ['source' => 'api_v1_location_update'], $claims);
        } elseif ((int) $current->location_id !== (int) $location->id) {
            $reanchored = $this->residenceService->reanchorPrimaryResidenceIfVerifiedReferenceEquivalent(
                $user,
                $location,
                ['source' => 'api_v1_verified_reference_upgrade'],
            );
            if ($reanchored === null) {
                $this->residenceService->transferPrimaryResidence(
                    $user,
                    $location,
                    $user,
                    'api_v1_location_update',
                    false,
                    $claims,
                );
            }
        } else {
            $this->residenceService->refreshPrimaryResidenceStructuralClaims($user, $location, $claims);
            $this->residenceService->clearPendingResidenceIntent($user, 'approved_location_selected');
        }

        return response()->json($this->summary($request));
    }

    private function summary(Request $request): array
    {
        $residence = $this->residenceService->currentPrimaryResidence($request->user());
        $residence?->loadMissing('location.type');
        $location = $residence?->location;

        return [
            'residence' => $residence === null ? null : [
                'id' => (int) $residence->id,
                'started_at' => $residence->started_at?->utc()?->toIso8601ZuluString(),
                'location' => $location === null ? null : [
                    'id' => (int) $location->id,
                    'identity' => 'location:'.$location->id,
                    'type_key' => $location->type?->key,
                    'status' => $location->status,
                ],
            ],
        ];
    }

    private function claimIds(Request $request): array
    {
        $raw = $request->query('location_structure_claim_ids', []);
        return is_array($raw) ? $raw : [$raw];
    }
}
