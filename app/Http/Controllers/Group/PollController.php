<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Events\GroupPollUpdated;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function store(Group $group, Request $request)
    {
        $inputs = $request->validate([
            'question' => 'required|string|max:255',
            'options' => 'required|array',
            'expires_at' => 'required|numeric',
            'type' => 'required|numeric|in:0,1',
            'skill_id' => 'nullable',
            'main_type' => 'required|numeric|in:0,1',
        ]);

        $inputs['expires_at'] = Carbon::now()->addDays($inputs['expires_at'])->format('Y-m-d H:i:s');
        $inputs['group_id'] = $group->id;
        $inputs['created_by'] = auth()->id();

        $poll = Poll::create($inputs);
        $poll->refresh();

        foreach ($inputs['options'] as $option) {
            PollOption::create([
                'poll_id' => $poll->id,
                'text' => $option,
            ]);
        }

        $this->dispatchGroupEvent(new \App\Events\PollCreated($poll, $group, auth()->user()));

        $poll->load(['options', 'votes', 'user', 'skill']);
        $payload = [
            'poll_id' => (int) $poll->id,
            'html' => view('groups.partials.poll', [
                'item' => $poll,
                'group' => $group,
                'userVote' => null,
            ])->render(),
        ];

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $group->id, 'poll_created', $payload, (int) auth()->id()));

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'نظرسنجی با موفقیت ایجاد شد.',
                'poll' => [
                    'id' => (int) $poll->id,
                    'html' => $payload['html'],
                ],
            ]);
        }

        return redirect()->back()->with('success', 'نظرسنجی شما با موفقیت ارسال شد!');
    }

    public function vote(Request $request, Poll $poll)
    {
        $request->validate([
            'option_id' => 'required|exists:poll_options,id',
        ]);

        if ($poll->votes()->where('user_id', auth()->id())->exists()) {
            $poll->votes()->where('user_id', auth()->id())->first()->delete();
        }

        $poll->votes()->create([
            'user_id' => auth()->id(),
            'option_id' => $request->option_id,
        ]);

        $activeMemberIds = GroupUser::where('group_id', $poll->group_id)
            ->where('status', 1)
            ->pluck('user_id')
            ->toArray();

        $voteCounts = PollVote::where('poll_id', $poll->id)
            ->whereIn('user_id', $activeMemberIds)
            ->selectRaw('option_id, COUNT(*) as votes_count')
            ->groupBy('option_id')
            ->pluck('votes_count', 'option_id');

        $totalVotes = (int) $voteCounts->sum();

        $options = $poll->options()
            ->select('id', 'text')
            ->get()
            ->map(function ($option) use ($voteCounts, $totalVotes) {
                $count = (int) ($voteCounts[$option->id] ?? 0);

                return [
                    'id' => (int) $option->id,
                    'text' => (string) $option->text,
                    'count' => (int) $count,
                    'percent' => $totalVotes > 0 ? (int) round(($count / $totalVotes) * 100) : 0,
                ];
            })
            ->values();

        $pollPayload = [
            'id' => (int) $poll->id,
            'user_option_id' => (int) $request->option_id,
            'total_votes' => (int) $totalVotes,
            'options' => $options,
        ];

        $this->dispatchGroupEvent(new GroupPollUpdated(
            (int) $poll->group_id,
            $pollPayload,
            (int) auth()->id()
        ));

        return response()->json([
            'status' => 'success',
            'poll' => $pollPayload,
        ]);
    }

    public function update(Request $request, Group $group, Poll $poll)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'expires_at' => 'nullable|numeric|min:1',
            'type' => 'nullable|numeric|in:0,1',
            'skill_id' => 'nullable',
        ]);

        $poll->update([
            'question' => $validated['question'],
            'expires_at' => now()->addDays((int) ($validated['expires_at'] ?? 3)),
            'real_type' => (int) ($validated['type'] ?? ($poll->real_type ?? 0)),
            'skill_id' => ((int) ($validated['type'] ?? ($poll->real_type ?? 0)) === 1) ? ($validated['skill_id'] ?? null) : null,
        ]);

        $poll->refresh();
        $poll->load(['options', 'votes', 'user', 'skill']);

        $eventPayload = [
            'poll_id' => (int) $poll->id,
            'html' => view('groups.partials.poll', [
                'item' => $poll,
                'group' => $group,
                'userVote' => null,
            ])->render(),
        ];
        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $group->id, 'poll_updated', $eventPayload, (int) auth()->id()));

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'نظرسنجی با موفقیت ویرایش شد.',
                'poll' => [
                    'id' => (int) $poll->id,
                    'html' => $eventPayload['html'],
                ],
            ]);
        }

        return redirect()->back()->with('success', 'نظرسنجی با موفقیت ویرایش شد.');
    }

    public function delete(Request $request, Group $group, Poll $poll)
    {
        $pollId = (int) $poll->id;
        $poll->delete();

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $group->id, 'poll_deleted', [
            'poll_id' => $pollId,
        ], (int) auth()->id()));

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'نظرسنجی با موفقیت حذف شد.',
                'poll_id' => $pollId,
            ]);
        }

        return redirect()->back()->with('success', 'نظرسنجی با موفقیت حذف شد.');
    }
    private function dispatchGroupEvent(object $event): void
    {
        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch(static fn () => event($event))->afterResponse();
            return;
        }

        event($event);
    }

    /**
     * Mark poll as read by current user
     */
    public function markAsRead(Poll $poll)
    {
        $user = auth()->user();
        
        // Don't mark own polls as read
        if ($poll->created_by === $user->id) {
            return response()->json(['status' => 'ignored']);
        }

        $poll->markAsRead($user->id);

        return response()->json([
            'status' => 'success',
            'read_count' => $poll->read_count,
        ]);
    }
}
