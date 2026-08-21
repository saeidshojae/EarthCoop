<?php

namespace App\Http\Controllers\Group;

use App\Enums\Elections\ElectionPosition;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Group;
use App\Models\Vote;
use App\Services\Elections\ElectionPolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
    ) {
    }

    public function submitVote(Request $request, Group $group)
    {
        // Resolve policy centrally even though the legacy submit path does not
        // yet enforce every policy invariant. E5 will move ballot validation
        // into the election domain and use this same resolver.
        $this->policyResolver->resolveForGroup($group);

        $inputs = $request->validate([
            'inspector' => 'nullable|array',
            'manager' => 'nullable|array',
        ]);

        $election = Election::where('group_id', $group->id)->where('is_closed', 0)->first();
        $voteCheck = Vote::where('voter_id', auth()->user()->id)->where('election_id', $election->id)->get();
        foreach ($voteCheck as $vote) {
            $vote->delete();
        }

        if (isset($inputs['inspector'])) {
            foreach ($inputs['inspector'] as $userId) {
                Vote::create([
                    'election_id' => $election->id,
                    'voter_id' => auth()->id(),
                    // Keep the legacy column populated during the compatibility
                    // window; canonical election code reads candidate_user_id.
                    'candidate_id' => $userId,
                    'candidate_user_id' => $userId,
                    'position' => ElectionPosition::Inspector->legacyVotePosition(),
                ]);
            }
        }

        if (isset($inputs['manager'])) {
            foreach ($inputs['manager'] as $userId) {
                Vote::create([
                    'election_id' => $election->id,
                    'voter_id' => auth()->id(),
                    'candidate_id' => $userId,
                    'candidate_user_id' => $userId,
                    'position' => ElectionPosition::Manager->legacyVotePosition(),
                ]);
            }
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'رأی شما با موفقیت ثبت شد.',
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

        if ($candidates[0]->accept_status != null) {
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

        $topOfInspectors = array_merge($topOfInspectors, $topOfManagers);
        $activeCandidates = $election->candidates()->whereIn('user_id', $topOfInspectors)->get();

        foreach ($activeCandidates as $candidate) {
            $candidate->accept_status = 1;
            $candidate->save();
        }

        // This remains the legacy close path until E3 replaces manual
        // finalisation with the canonical lifecycle service/state machine.
        $election->update(['is_closed' => 1]);

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
