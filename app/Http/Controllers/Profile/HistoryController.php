<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Election;
use App\Models\Poll;
use App\Models\Reaction;
use App\Models\PollVote;
use App\Models\Vote;
use App\Services\Elections\CurrentElectionCenterService;
use App\Services\ParticipationPointSummaryService;

class HistoryController extends Controller
{
    public function index(ParticipationPointSummaryService $participationPointSummaryService)
    {
        $userId = (int) auth()->id();

        $blogs = Blog::where('user_id', $userId)
            ->with(['group', 'likes', 'dislikes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $comments = Comment::where('user_id', $userId)
            ->with(['blog.group', 'parent', 'likes', 'dislikes', 'childs'])
            ->orderBy('created_at', 'desc')
            ->get();

        $reactions = Reaction::where('user_id', $userId)
            ->with(['blog.group', 'comment.blog.group'])
            ->orderBy('created_at', 'desc')
            ->get();

        $polls = PollVote::where('user_id', $userId)
            ->with(['poll.group', 'option'])
            ->orderBy('created_at', 'desc')
            ->get();

        $elections = Vote::where('voter_id', $userId)
            ->with(['candidateUser', 'election.group'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pointTransactions = \App\Models\UserPointTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $pointSummary = $participationPointSummaryService->forUser($userId);
        $reputationBreakdown = $pointSummary['reputation_breakdown'];
        $currentPoints = (int) $pointSummary['total_points'];
        $convertibleAwardedPoints = (int) $pointSummary['convertible_awarded_points'];
        $ledgerConsumedPoints = (int) $pointSummary['ledger_consumed_points'];
        $remainingConvertiblePoints = (int) $pointSummary['remaining_convertible_points'];
        $legacyConvertedPoints = (int) $pointSummary['legacy_cashed_points'];
        $reversalAdjustmentPoints = (int) $pointSummary['participation_reversal_points'];
        $reputationLevelLabel = (string) $pointSummary['level_label'];

        return view('history.index', compact(
            'blogs',
            'comments',
            'reactions',
            'polls',
            'elections',
            'pointTransactions',
            'currentPoints',
            'pointSummary',
            'reputationBreakdown',
            'convertibleAwardedPoints',
            'ledgerConsumedPoints',
            'remainingConvertiblePoints',
            'legacyConvertedPoints',
            'reversalAdjustmentPoints',
            'reputationLevelLabel'
        ));
    }

    public function election(CurrentElectionCenterService $currentElectionCenterService)
    {
        $snapshot = $currentElectionCenterService->forUser(auth()->user());

        return view('history.election', compact('snapshot'));
    }

    public function electionHistory()
    {
        $userId = (int) auth()->id();

        $currentElections = Election::query()
            ->whereHas('group.users', fn ($query) => $query->whereKey($userId))
            ->with([
                'group',
                'policyVersion',
                'yourVotes.candidateUser',
                'responsibilityOffers' => fn ($query) => $query->where('candidate_user_id', $userId)->orderByDesc('id'),
                'appointments' => fn ($query) => $query->where('user_id', $userId)->orderByDesc('id'),
            ])
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->get();

        return view('history.election-history', compact('currentElections'));
    }

    public function poll()
    {
        $user = auth()->user();

        $polls = Poll::whereHas('group.users', function ($query) use ($user) {
            $query->whereKey($user->id);
        })
            ->where('main_type', 1)
            ->with(['group', 'options', 'yourVote.option'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('history.poll', compact('polls'));
    }
}
