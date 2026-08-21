<?php

namespace App\Http\Controllers\Group;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Group;
use App\Models\Vote;
use App\Services\Elections\ElectionBallotService;
use App\Services\Elections\ElectionLifecycleService;
use App\Services\Elections\ElectionPolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionBallotService $ballots,
    ) {
    }

    public function submitVote(Request $request, Group $group)
    {
        $inputs = $request->validate([
            'inspector' => 'nullable|array',
            'manager' => 'nullable|array',
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

        $result = $this->ballots->submit(
            $election,
            (int) auth()->id(),
            $inputs['manager'] ?? [],
            $inputs['inspector'] ?? [],
            $request->header('Idempotency-Key') ?: null,
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

    public function finishElection(Election $election)
    {
        $this->authorize('manageSession', $election->group);

        $groupSetting = $this->policyResolver->resolveForGroup($election->group);
        $candidates = $election->candidates;

        foreach ($candidates as $candidate) {
            $candidate->accept_status = null;
            $candidate->save();
        }

        if ($candidates->isNotEmpty() && $candidates[0]->accept_status != null) {
            return response()->json([
                'status' => 'error',
                'error' => 'پیش از اتمام انتخابات امکان انتخاب دیگری وجود ندارد',
            ]);
        }

        foreach ($candidates as $candidate) {
            $candidate->accept_status = 0;
            $candidate->save();
        }

        $topOfInspectors = Vote::select('candidate_id', DB::raw('COUNT(*) as total_votes'))
            ->where('election_id', $election->id)
            ->where('position', ElectionPosition::Inspector->legacyVotePosition())
            ->groupBy('candidate_id')
            ->orderBy('total_votes', 'desc')
            ->take($this->policyResolver->inspectorSeatCount($groupSetting))
            ->get()
            ->pluck('candidate_id')
            ->toArray();

        $topOfManagers = Vote::select('candidate_id', DB::raw('COUNT(*) as total_votes'))
            ->where('election_id', $election->id)
            ->where('position', ElectionPosition::Manager->legacyVotePosition())
            ->groupBy('candidate_id')
            ->orderBy('total_votes', 'desc')
            ->take($this->policyResolver->managerSeatCount($groupSetting))
            ->get()
            ->pluck('candidate_id')
            ->toArray();

        $selectedUserIds = array_merge($topOfInspectors, $topOfManagers);
        $activeCandidates = $election->candidates()->whereIn('user_id', $selectedUserIds)->get();

        foreach ($activeCandidates as $candidate) {
            $candidate->accept_status = 1;
            $candidate->save();
        }

        // Legacy/manual completion is now only an adapter into the canonical
        // lifecycle state machine. It no longer mutates is_closed/status itself.
        $election = $this->lifecycle->transition(
            $election,
            ElectionLifecycleStatus::Closed,
            'legacy_manual_finish_adapter',
            'legacy_controller',
            (int) auth()->id(),
            'election-controller:finish',
        );

        app(\App\Services\GroupChat\GroupEventPublisher::class)->publish(
            new \App\Events\GroupFeedUpdated((int) $election->group_id, 'election_finished', [
                'election_id' => (int) $election->id,
                'is_closed' => true,
                'elected_candidate_ids' => $activeCandidates->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
            ], (int) auth()->id()),
        );

        return response()->json([
            'status' => 'success',
            'candidates' => $activeCandidates,
            'group_setting' => $groupSetting,
        ]);
    }
}
