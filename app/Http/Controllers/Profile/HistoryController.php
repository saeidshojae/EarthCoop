<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Election;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Reaction;
use Illuminate\Http\Request;
use App\Models\PollVote;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    public function index(){
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
        
        $elections = Vote::where('voter_id', auth()->user()->id)
            ->with(['user', 'election.group'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $pointTransactions = \App\Models\UserPointTransaction::where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $currentPoints = optional(auth()->user()->points)->points ?? 0;

        return view('history.index', compact('blogs', 'comments', 'reactions', 'polls', 'elections', 'pointTransactions', 'currentPoints'));
    }

    public function election(){


        $currentElections = Election::with(['group', 'yourVotes.user'])
        ->whereHas('group.users', function ($query) {
            $query->whereKey(auth()->id());  // به جای where('users.id', ...)
        })
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>=', now())
        ->get();
    

        return view('history.election', compact('currentElections'));
    }

    public function poll(){
        $user = auth()->user();
        
        // Get all polls from user's groups with eager loading
        $polls = Poll::whereHas('group.users', function ($query) use ($user) {
            $query->whereKey($user->id);
        })
        ->with(['group', 'options', 'yourVote.option'])
        ->orderBy('created_at', 'desc')
        ->get();
        
        return view('history.poll', compact('polls'));
    }
}
