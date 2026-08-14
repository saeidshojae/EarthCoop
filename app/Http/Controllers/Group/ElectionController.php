<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElectionController extends Controller
{
    public function submitVote(Request $request, Group $group)
    {
        $groupSetting = GroupSetting::where('level', $group->location_level)->first();
        
        if($group->specialty_id != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_job')->first();
        }elseif($group->experience_id != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_experience')->first();
        }elseif($group->age_group_id != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_age')->first();
        }elseif($group->gender != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_gender')->first();
        }
        
        $inputs = $request->validate([
            'inspector' => 'nullable|array',
            'manager' => 'nullable|array',
        ]);
        
        $election = Election::where('group_id', $group->id)->where('is_closed', 0)->first();       
        $voteCheck = Vote::where('voter_id', auth()->user()->id)->where('election_id', $election->id)->get();
        foreach($voteCheck as $vote){
            $vote->delete();
        }

        
        if(isset($inputs['inspector'])){
            foreach ($inputs['inspector'] as $userId) {
                Vote::create([
                    'election_id' => $election->id,
                    'voter_id' => auth()->id(),
                    'candidate_id' => $userId,
                    'position' => 0
                ]);
            }            
        }

        if(isset($inputs['manager'])){
            foreach ($inputs['manager'] as $userId) {
                Vote::create([
                    'election_id' => $election->id,
                    'voter_id' => auth()->id(),
                    'candidate_id' => $userId,
                    'position' => 1
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

    public function finishElection(Election $election){
        $this->authorize('manageSession', $election->group);
        $groupSetting = GroupSetting::where('level', $election->group->location_level)->first();
        $candidates = $election->candidates;
        foreach($candidates as $candidate){
            $candidate->accept_status = null;
            $candidate->save();
        }
        if($candidates[0]->accept_status != null){
            return response()->json([
                'status' => 'error',
                'error' => 'پیش از اتمام انتخابات امکان انتخاب دیگری وجود ندارد',
            ]);
            exit;
        }
        foreach($candidates as $candidate){
            $candidate->accept_status = 0;
            $candidate->save();
        }

        $topOfInspectors = Vote::select('candidate_id', DB::raw('COUNT(*) as total_votes'))
            ->where('election_id', $election->id)
            ->where('position', 0)        // 0 = بازرس
            ->groupBy('candidate_id')
            ->orderBy('total_votes', 'desc')
            ->take($groupSetting->insperctor_count)
            ->get()->pluck('candidate_id')->toArray();
        
        $topOfManagers = Vote::select('candidate_id', DB::raw('COUNT(*) as total_votes'))
            ->where('election_id', $election->id)
            ->where('position', 1)        // 0 = بازرس
            ->groupBy('candidate_id')
            ->orderBy('total_votes', 'desc')
            ->take($groupSetting->manager_count)
            ->get()->pluck('candidate_id')->toArray();

        $topOfInspectors = array_merge($topOfInspectors, $topOfManagers);
        $activeCandidates =  $election->candidates()->whereIn('user_id', $topOfInspectors)->get();

        foreach($activeCandidates as $candidate){
            $candidate->accept_status = 1;
            $candidate->save();
        }

        // Close the election
        $election->update(['is_closed' => 1]);

        app(\App\Services\GroupChat\GroupEventPublisher::class)->publish(
            new \App\Events\GroupFeedUpdated((int) $election->group_id, 'election_finished', [
                'election_id' => (int) $election->id,
                'is_closed' => true,
                'elected_candidate_ids' => $activeCandidates->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
            ], (int) auth()->id())
        );

        return response()->json([
            'status' => 'success',
            'candidates' => $activeCandidates,
            'group_setting' => $groupSetting
            
        ]);
    }
}
