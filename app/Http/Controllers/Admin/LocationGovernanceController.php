<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\PendingResidenceIntent;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\NajmHoda\LocationGovernanceReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $verificationThreshold = max(1, (int) config('location-governance.location_proposal_verification_threshold', 10));

        $proposalQuery = LocationProposal::query()
            ->with(['parentLocation', 'type', 'proposer'])
            ->withCount('evidence')
            ->whereIn('status', $openStatuses);

        if ($proposalStatusFilter !== null) {
            $proposalQuery->where('status', $proposalStatusFilter);
        }

        $proposals = $proposalQuery
            ->latest('id')
            ->limit(100)
            ->get();

        $hodaReviews = $proposals
            ->mapWithKeys(fn (LocationProposal $proposal): array => [
                $proposal->id => $reviewService->review($proposal),
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
            'hodaReviews' => $hodaReviews,
            'referenceLocations' => $referenceLocations,
            'officialTopology' => $officialTopology,
            'communityAreas' => $communityAreas,
            'importRuns' => $importRuns,
            'healthDiagnostics' => $healthDiagnostics,
            'governanceSummary' => $governanceSummary,
            'verificationThreshold' => $verificationThreshold,
        ]);
    }

    public function approve(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $this->validateReason($request);
        $location = $proposalService->approve($locationProposal, $request->user(), $validated['reason']);

        return $this->reviewResponse($request, $locationProposal->fresh(), ['location_id' => $location->id]);
    }

    public function reject(
        Request $request,
        LocationProposal $locationProposal,
        LocationProposalService $proposalService,
    ): JsonResponse|RedirectResponse {
        $validated = $this->validateReason($request);
        $proposalService->reject($locationProposal, $request->user(), $validated['reason']);

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
        $proposalService->merge($locationProposal, $existing, $request->user(), $validated['reason']);

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
        $proposalService->requestMoreEvidence($locationProposal, $request->user(), $validated['reason']);

        return $this->reviewResponse($request, $locationProposal->fresh());
    }

    /** @return array{reason: string} */
    private function validateReason(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'min:4', 'max:1000'],
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
