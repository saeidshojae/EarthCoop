<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\NajmHoda\LocationGovernanceReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocationGovernanceController extends Controller
{
    public function index(LocationGovernanceReviewService $reviewService): View
    {
        $proposals = LocationProposal::query()
            ->with(['parentLocation', 'type', 'proposer'])
            ->withCount('evidence')
            ->whereIn('status', [
                LocationProposalStatus::Pending->value,
                LocationProposalStatus::ReadyForReview->value,
                LocationProposalStatus::NeedsEvidence->value,
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        $hodaReviews = $proposals
            ->mapWithKeys(fn (LocationProposal $proposal): array => [
                $proposal->id => $reviewService->review($proposal),
            ]);

        $importRuns = DB::table('location_import_runs')
            ->latest('id')
            ->limit(10)
            ->get();

        $governanceSummary = [
            'active' => GovernanceArea::query()->active()->count(),
            'official' => GovernanceArea::query()->official()->count(),
            'community' => GovernanceArea::query()->where('area_kind', 'community')->count(),
        ];

        return view('admin.location-governance.index', [
            'proposals' => $proposals,
            'hodaReviews' => $hodaReviews,
            'importRuns' => $importRuns,
            'governanceSummary' => $governanceSummary,
            'verificationThreshold' => max(1, (int) config('location-governance.location_proposal_verification_threshold', 10)),
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
