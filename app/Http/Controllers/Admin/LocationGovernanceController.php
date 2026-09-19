<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\PendingResidenceIntent;
use App\Models\Setting;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\Admin\AdminSettingManagementService;
use App\Services\NajmHoda\LocationGovernanceReviewService;
use App\Support\LocationDisplayName;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationGovernanceController extends Controller
{
    public function index(Request $request, LocationGovernanceReviewService $reviewService): View
    {
        $openStatuses = [
            LocationProposalStatus::Pending->value,
            LocationProposalStatus::ReadyForReview->value,
            LocationProposalStatus::NeedsEvidence->value,
        ];
        $requestedStatus = $request->query('proposal_status');
        $proposalStatusFilter = is_string($requestedStatus) && in_array($requestedStatus, $openStatuses, true)
            ? $requestedStatus
            : null;
        $settings = Setting::singleton();
        $verificationThreshold = max(1, (int) ($settings->location_proposal_verification_threshold ?? config('location-governance.location_proposal_verification_threshold', 10)));
        $structureClaimVerificationThreshold = max(1, (int) ($settings->location_structure_claim_verification_threshold ?? config('location-governance.location_structure_claim_verification_threshold', 10)));

        $proposalQuery = LocationProposal::query()
            ->with(['parentLocation', 'parentProposal', 'type', 'proposer'])
            ->withCount('evidence')
            ->withCount([
                'childProposals as open_child_proposals_count' => fn ($query) => $query->whereIn('status', $openStatuses),
            ])
            ->whereIn('status', $openStatuses);

        if ($proposalStatusFilter !== null) {
            $proposalQuery->where('status', $proposalStatusFilter);
        }

        $proposals = $proposalQuery
            ->latest('id')
            ->limit(100)
            ->get();

        $proposalPaths = $proposals->mapWithKeys(fn (LocationProposal $proposal): array => [
            $proposal->id => $this->proposalPath($proposal),
        ]);

        $hodaReviews = $proposals
            ->mapWithKeys(fn (LocationProposal $proposal): array => [
                $proposal->id => $reviewService->review($proposal),
            ]);

        $structureClaims = LocationStructureClaim::query()
            ->with(['location.type', 'proposer'])
            ->withCount('evidence')
            ->whereIn('status', LocationStructureClaimService::OPEN_STATUSES)
            ->latest('id')
            ->limit(100)
            ->get();

        $structureClaimPaths = $structureClaims->mapWithKeys(fn (LocationStructureClaim $claim): array => [
            $claim->id => $this->locationPath($claim->location),
        ]);

        $referenceLocations = Location::query()
            ->with(['parent', 'type'])
            ->where('status', 'active')
            ->orderBy('country_code')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $officialTopology = GovernanceArea::query()
            ->official()
            ->with(['parent', 'locations'])
            ->orderBy('rank')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $communityAreas = GovernanceArea::query()
            ->where('area_kind', 'community')
            ->with(['parent', 'locations'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $importRuns = DB::table('location_import_runs')
            ->latest('id')
            ->limit(10)
            ->get();

        $pendingIntentSample = PendingResidenceIntent::query()
            ->where('status', 'pending')
            ->with(['anchorRelationship', 'locationProposal'])
            ->latest('id')
            ->limit(1000)
            ->get();

        $healthDiagnostics = [
            'open_proposals' => LocationProposal::query()->whereIn('status', $openStatuses)->count(),
            'above_threshold_proposals' => LocationProposal::query()
                ->whereIn('status', $openStatuses)
                ->has('evidence', '>=', $verificationThreshold)
                ->count(),
            'pending_residence_intents' => PendingResidenceIntent::query()->where('status', 'pending')->count(),
            'invalid_pending_residence_intents' => $pendingIntentSample
                ->filter(function (PendingResidenceIntent $intent) use ($openStatuses): bool {
                    $anchor = $intent->anchorRelationship;
                    $proposal = $intent->locationProposal;
                    $proposalStatus = $proposal?->status instanceof LocationProposalStatus
                        ? $proposal->status->value
                        : $proposal?->status;

                    return $anchor === null
                        || $proposal === null
                        || $anchor->relationship_type !== 'primary_residence'
                        || $anchor->ended_at !== null
                        || ! in_array($proposalStatus, $openStatuses, true);
                })
                ->count(),
            'locations_missing_schema_or_type' => Location::query()
                ->where(function ($query): void {
                    $query->whereNull('location_schema_id')
                        ->orWhereNull('location_type_id');
                })
                ->count(),
            'official_areas_without_location_mapping' => GovernanceArea::query()
                ->active()
                ->official()
                ->doesntHave('locations')
                ->count(),
        ];

        $governanceSummary = [
            'active' => GovernanceArea::query()->active()->count(),
            'official' => GovernanceArea::query()->official()->count(),
            'community' => GovernanceArea::query()->where('area_kind', 'community')->count(),
        ];

        return view('admin.location-governance.index', [
            'proposals' => $proposals,
            'proposalStatusFilter' => $proposalStatusFilter,
            'proposalPaths' => $proposalPaths,
            'structureClaims' => $structureClaims,
            'structureClaimPaths' => $structureClaimPaths,
            'hodaReviews' => $hodaReviews,
            'referenceLocations' => $referenceLocations,
            'officialTopology' => $officialTopology,
            'communityAreas' => $communityAreas,
            'importRuns' => $importRuns,
            'healthDiagnostics' => $healthDiagnostics,
            'governanceSummary' => $governanceSummary,
            'verificationThreshold' => $verificationThreshold,
            'structureClaimVerificationThreshold' => $structureClaimVerificationThreshold,
        ]);
    }

    public function updateSettings(Request $request, AdminSettingManagementService $settingsService): RedirectResponse
    {
        $validated = $request->validate([
            'location_proposal_verification_threshold' => ['required', 'integer', 'min:1', 'max:1000000'],
            'location_structure_claim_verification_threshold' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        foreach ($validated as $key => $value) {
            $settingsService->change($key, $value);
        }

        return back()->with('success', 'تنظیمات حد حمایت مکان و ادعاهای ساختاری ذخیره شد.');
    }

    public function update(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'canonical_name' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:4', 'max:1000'],
        ]);

        try {
            $proposalService->rename(
                $locationProposal,
                $request->user(),
                $validated['canonical_name'],
                $validated['reason'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['proposal' => $exception->getMessage()]);
        }

        return $this->reviewResponse($request, $locationProposal->fresh());
    }

    public function approve(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $this->validateReason($request);

        try {
            $location = $proposalService->approve($locationProposal, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            throw $this->proposalValidationException($exception);
        }

        return $this->reviewResponse($request, $locationProposal->fresh(), ['location_id' => $location->id]);
    }

    public function reject(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $this->validateReason($request);

        try {
            $proposalService->reject($locationProposal, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            throw $this->proposalValidationException($exception);
        }

        return $this->reviewResponse($request, $locationProposal->fresh());
    }

    public function merge(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:4', 'max:1000'],
            'existing_location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        $existing = Location::query()->findOrFail((int) $validated['existing_location_id']);

        try {
            $proposalService->merge($locationProposal, $existing, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            throw $this->proposalValidationException($exception);
        }

        return $this->reviewResponse($request, $locationProposal->fresh(), [
            'location_id' => $existing->id,
        ]);
    }

    public function requestEvidence(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $this->validateReason($request);

        try {
            $proposalService->requestMoreEvidence($locationProposal, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            throw $this->proposalValidationException($exception);
        }

        return $this->reviewResponse($request, $locationProposal->fresh());
    }

    /** @return array<int, array{kind: string, label: string, pending: bool}> */
    private function proposalPath(LocationProposal $proposal): array
    {
        $proposalChain = [];
        $cursor = $proposal;
        $visited = [];

        while ($cursor !== null && ! isset($visited[$cursor->id])) {
            $visited[$cursor->id] = true;
            $proposalChain[] = $cursor;

            if ($cursor->parent_location_id !== null) {
                $location = $cursor->parentLocation()->first();
                break;
            }

            $cursor = $cursor->parentProposal()->first();
        }

        $locationChain = [];
        while (isset($location) && $location !== null) {
            $locationChain[] = $location;
            $location = $location->parent()->first();
        }

        $path = [];
        foreach (array_reverse($locationChain) as $item) {
            $path[] = ['kind' => 'location', 'label' => LocationDisplayName::for($item), 'pending' => false];
        }
        foreach (array_reverse($proposalChain) as $item) {
            $path[] = ['kind' => 'proposal', 'label' => LocationDisplayName::for($item), 'pending' => true];
        }

        return $path;
    }

    /** @return array<int, string> */
    private function locationPath(?Location $location): array
    {
        $path = [];
        $visited = [];

        while ($location !== null && ! isset($visited[$location->id])) {
            $visited[$location->id] = true;
            $path[] = LocationDisplayName::for($location);
            $location = $location->parent()->first();
        }

        return array_reverse($path);
    }

    public function approveStructureClaim(Request $request, LocationStructureClaim $locationStructureClaim, LocationStructureClaimService $service): JsonResponse|RedirectResponse
    {
        return $this->reviewStructureClaim($request, $locationStructureClaim, $service, 'approve');
    }

    public function rejectStructureClaim(Request $request, LocationStructureClaim $locationStructureClaim, LocationStructureClaimService $service): JsonResponse|RedirectResponse
    {
        return $this->reviewStructureClaim($request, $locationStructureClaim, $service, 'reject');
    }

    public function requestStructureClaimEvidence(Request $request, LocationStructureClaim $locationStructureClaim, LocationStructureClaimService $service): JsonResponse|RedirectResponse
    {
        return $this->reviewStructureClaim($request, $locationStructureClaim, $service, 'markNeedsEvidence');
    }

    private function reviewStructureClaim(Request $request, LocationStructureClaim $claim, LocationStructureClaimService $service, string $action): JsonResponse|RedirectResponse
    {
        $validated = $this->validateReason($request);

        try {
            $reviewed = $service->{$action}($claim, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['structure_claim' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['claim_id' => $reviewed->id, 'status' => $reviewed->status]);
        }

        return back()->with('success', 'تصمیم مدیر دربارهٔ ادعای ساختاری ثبت شد.');
    }

    /** @return array{reason: string} */
    private function validateReason(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'min:4', 'max:1000'],
        ]);
    }

    private function proposalValidationException(DomainException $exception): ValidationException
    {
        return ValidationException::withMessages([
            'proposal' => $exception->getMessage(),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function reviewResponse(Request $request, LocationProposal $proposal, array $extra = []): JsonResponse|RedirectResponse
    {
        $status = $proposal->status instanceof LocationProposalStatus
            ? $proposal->status->value
            : (string) $proposal->status;

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'proposal_id' => $proposal->id,
                'status' => $status,
            ], $extra));
        }

        return back()->with('success', 'تصمیم مدیر ثبت و در سابقهٔ بازبینی ذخیره شد.');
    }
}
