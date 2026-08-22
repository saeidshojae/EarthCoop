<?php

namespace App\Http\Controllers\Group;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionVoteVisibility;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Group;
use App\Services\Elections\ElectionBallotService;
use App\Services\Elections\ElectionLifecycleService;
use App\Services\Elections\ElectionPolicyResolver;
use App\Services\Elections\ElectionTallyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionBallotService $ballots,
        private readonly ElectionTallyService $tally,
    ) {
    }

    public function submitVote(Request $request, Group $group)
    {
        $inputs = $request->validate([
            'inspector' => 'nullable|array',
            'manager' => 'nullable|array',
            'vote_visibility' => 'nullable|array',
            'vote_visibility.*' => [
                Rule::in(array_map(
                    fn (ElectionVoteVisibility $visibility) => $visibility->value,
                    ElectionVoteVisibility::cases(),
                )),
            ],
            'comment' => 'nullable|string|max:4000',
            'comment_visibility' => [
                'nullable',
                Rule::in(array_map(
                    fn (ElectionBallotCommentVisibility $visibility) => $visibility->value,
                    ElectionBallotCommentVisibility::cases(),
                )),
            ],
            'comment_anonymous' => 'nullable|boolean',
        ]);

        $election = Election::query()
            ->where('group_id', $group->id)
            ->where('lifecycle_status', ElectionLifecycleStatus::Open->value)
            ->orderByDesc('id')
            ->first();

        if ($election === null) {
            throw ValidationException::withMessages([
                'election' => 'در حال حاضر انتخابات بازی برای این گروه وجود ندارد.',
            ]);
        }

        $commentVisibility = isset($inputs['comment_visibility'])
            ? ElectionBallotCommentVisibility::from($inputs['comment_visibility'])
            : null;

        $result = $this->ballots->submit(
            $election,
            (int) auth()->id(),
            $inputs['manager'] ?? [],
            $inputs['inspector'] ?? [],
            $request->header('Idempotency-Key') ?: null,
            $inputs['comment'] ?? null,
            $commentVisibility,
            $inputs['vote_visibility'] ?? [],
            (bool) ($inputs['comment_anonymous'] ?? false),
        );

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'رأی شما با موفقیت ثبت شد.',
                'ballot' => $result,
            ]);
        }

        return redirect()->back()->with('success', 'رأی شما با موفقیت ثبت شد.');
    }

    /**
     * Legacy endpoint adapter only. Ranking now comes exclusively from the
     * canonical E6 stop snapshot/tally service; this controller no longer
     * computes top-N from the live votes projection.
     */
    public function finishElection(Election $election)
    {
        $this->authorize('manageSession', $election->group);

        $groupSetting = $this->policyResolver->resolveForGroup($election->group);
        $candidates = $election->candidates;

        foreach ($candidates as $candidate) {
            $candidate->accept_status = 0;
            $candidate->save();
        }

        $election = $this->lifecycle->transition(
            $election,
            ElectionLifecycleStatus::Closed,
            'legacy_manual_finish_adapter',
            'legacy_controller',
            (int) auth()->id(),
            'election-controller:finish',
        );

        $tallyRows = $this->tally->tally($election);
        $selectedUserIds = $tallyRows
            ->where('within_seat_cutoff', true)
            ->pluck('candidate_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $activeCandidates = $election->candidates()->whereIn('user_id', $selectedUserIds)->get();
        foreach ($activeCandidates as $candidate) {
            // Compatibility projection only; E7 moves offer/acceptance out of
            // Candidate/ProfileController into the election domain.
            $candidate->accept_status = 1;
            $candidate->save();
        }

        app(\App\Services\GroupChat\GroupEventPublisher::class)->publish(
            new \App\Events\GroupFeedUpdated((int) $election->group_id, 'election_finished', [
                'election_id' => (int) $election->id,
                'is_closed' => true,
                'elected_candidate_ids' => $selectedUserIds->all(),
            ], (int) auth()->id()),
        );

        return response()->json([
            'status' => 'success',
            'candidates' => $activeCandidates,
            'group_setting' => $groupSetting,
        ]);
    }
}
