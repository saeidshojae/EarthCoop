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

class HistoryController extends Controller
{
    public function index()
    {
        $blogs = Blog::where('user_id', auth()->user()->id)
            ->with(['group', 'likes', 'dislikes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $comments = Comment::where('user_id', auth()->user()->id)
            ->with(['blog.group', 'parent', 'likes', 'dislikes', 'childs'])
            ->orderBy('created_at', 'desc')
            ->get();

        $reactions = Reaction::where('user_id', auth()->user()->id)
            ->with(['blog.group', 'comment.blog.group'])
            ->orderBy('created_at', 'desc')
            ->get();

        $polls = PollVote::where('user_id', auth()->user()->id)
            ->with(['poll.group', 'option'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Compatibility list for the generic activity page. Use the canonical
        // selected-member relation rather than the overloaded legacy candidate_id.
        $elections = Vote::where('voter_id', auth()->user()->id)
            ->with(['candidateUser', 'election.group'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pointTransactions = \App\Models\UserPointTransaction::where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $pointRecord = \App\Models\UserPoint::where('user_id', auth()->user()->id)->first();
        $currentPoints = $pointRecord ? (int) $pointRecord->points : (int) (\App\Models\UserPointTransaction::where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'desc')
            ->value('balance_after') ?? 0);

        return view('history.index', compact('blogs', 'comments', 'reactions', 'polls', 'elections', 'pointTransactions', 'currentPoints'));
    }

    public function election()
    {
        $userId = (int) auth()->id();

        // History is lifecycle-driven, not wall-clock driven: tallying,
        // acceptance, appointment and completed cycles remain visible.
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

        return view('history.election', compact('currentElections'));
    }

    public function poll()
    {
        $user = auth()->user();

        $polls = Poll::whereHas('group.users', function ($query) use ($user) {
            $query->whereKey($user->id);
        })
            ->with(['group', 'options', 'yourVote.option'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('history.poll', compact('polls'));
    }
}
