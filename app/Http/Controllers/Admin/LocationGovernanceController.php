<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\PendingResidenceIntent;
use App\Models\ReferenceSettlement;
use App\Models\Setting;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ReferenceSettlementReviewService;
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
            ->with(['parentLocation', 'parentProposal', 'parentReferenceSettlement', 'type', 'proposer'])
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

        $settlementClaimReviewThreshold = max(1, (int) config('iran_settlement_catalog.claim_review_threshold', 10));
        $settlementReviewQueue = ReferenceSettlement::query()
            ->withCount([
                'residenceClaims as open_residence_claims_count' => fn ($query) => $query->whereIn('status', ['pending', 'needs_evidence']),
            ])
            ->whereHas('residenceClaims', fn ($query) => $query->whereIn('status', ['pending', 'needs_evidence']))
            ->orderByDesc('open_residence_claims_count')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

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
            ->with(['anchorRelationship', 'locationProposal.type', 'locationProposal.parentReferenceSettlement', 'referenceSettlementResidenceClaim.settlement'])
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
                    $settlementClaim = $intent->referenceSettlementResidenceClaim;
                    $proposalStatus = $proposal?->status instanceof LocationProposalStatus
                        ? $proposal->status->value
                        : $proposal?->status;
                    $proposalValid = $proposal !== null && in_array($proposalStatus, $openStatuses, true);
                    $settlementClaimValid = $settlementClaim !== null
                        && (int) $settlementClaim->user_id === (int) $intent->user_id
                        && in_array($settlementClaim->status, ['pending', 'needs_evidence', 'residential_evidence_verified'], true)
                        && $settlementClaim->settlement !== null;
                    $combinedValid = $proposalValid
                        && $settlementClaimValid
                        && $proposal->type?->key === 'neighborhood'
                        && (int) $proposal->parent_reference_settlement_id === (int) $settlementClaim->reference_settlement_id;
                    $targetValid = ($proposalValid && $settlementClaim === null)
                        || ($settlementClaimValid && $proposal === null)
                        || $combinedValid;

                    return $anchor === null
                        || $anchor->relationship_type !== 'primary_residence'
                        || $anchor->ended_at !== null
                        || ! $targetValid;
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
            'settlementReviewQueue' => $settlementReviewQueue,
            'settlementClaimReviewThreshold' => $settlementClaimReviewThreshold,
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
            if ($cursor->parent_reference_settlement_id !== null) {
                $referenceSettlement = $cursor->parentReferenceSettlement()->first();
                if ($referenceSettlement !== null) {
                    try {
                        $location = app(\App\Services\LocationGovernance\IranSettlementAnchorResolver::class)->resolve($referenceSettlement);
                    } catch (\Throwable) {
                        $location = null;
                    }
                }
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
        if (isset($referenceSettlement) && $referenceSettlement !== null) {
            $label = trim((string) $referenceSettlement->name_fa);
            $path[] = [
                'kind' => 'reference_settlement',
                'label' => str_starts_with($label, 'آبادی ') || str_starts_with($label, 'روستای ') ? $label : 'آبادی '.$label,
                'pending' => true,
            ];
        }
        foreach (array_reverse($proposalChain) as $item) {
            $path[] = ['kind' => 'proposal', 'label' => LocationDisplayName::for($item), 'pending' => true];
        }

        return $path;
    }

    public function reviewSettlement(
        Request $request,
        ReferenceSettlement $referenceSettlement,
        ReferenceSettlementReviewService $service,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', 'in:needs_evidence,verified_residential_village,verified_nonresidential_place'],
            'reason' => ['required', 'string', 'min:4', 'max:1000'],
            'evidence_source' => ['nullable', 'string', 'max:255'],
            'evidence_date' => ['nullable', 'date', 'before_or_equal:today'],
            'evidence_reference' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $review = $service->review(
                $referenceSettlement,
                $request->user(),
                $validated['decision'],
                $validated['reason'],
                $validated['evidence_source'] ?? null,
                $validated['evidence_date'] ?? null,
                $validated['evidence_reference'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'settlement' => $exception->getMessage(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'settlement_id' => $referenceSettlement->id,
                'review_id' => $review->id,
                'decision' => $review->decision,
                'classification' => $referenceSettlement->fresh()->classification,
                'governance_authorized' => false,
            ]);
        }

        return back()->with('success', 'بازبینی آبادی ثبت شد؛ این تصمیم به‌تنهایی حوزهٔ حکمرانی یا اقامت رسمی ایجاد نمی‌کند.');
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
