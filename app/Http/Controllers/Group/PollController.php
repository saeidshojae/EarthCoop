<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Events\GroupPollUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StorePollRequest;
use App\Http\Requests\Group\VotePollRequest;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GroupChat\GroupFeedService;

class PollController extends Controller
{
    public function store(Group $group, StorePollRequest $request)
    {
        $inputs = $request->validated();

        $inputs['expires_at'] = Carbon::now()->addDays($inputs['expires_at'])->format('Y-m-d H:i:s');
        $inputs['group_id'] = $group->id;
        $inputs['created_by'] = auth()->id();

        $poll = DB::transaction(function () use ($inputs): Poll {
            $options = $inputs['options'];
            unset($inputs['options']);
            $poll = Poll::create($inputs);
            $poll->options()->createMany(collect($options)->map(fn ($option) => ['text' => $option])->all());
            app(GroupFeedService::class)->record((int) $poll->group_id, 'poll', (int) $poll->id, (int) $poll->created_by, $poll->created_at);

            return $poll->refresh();
        });

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

    public function vote(VotePollRequest $request, Poll $poll)
    {
        abort_if(! $poll->is_active || $poll->isExpired(), 422, 'This poll is not accepting votes.');

        DB::transaction(function () use ($request, $poll): void {
            Poll::whereKey($poll->id)->lockForUpdate()->firstOrFail();
            $poll->votes()->where('user_id', auth()->id())->delete();
            $poll->votes()->create([
                'user_id' => auth()->id(),
                'option_id' => $request->validated('option_id'),
            ]);
            app(GroupFeedService::class)->recordMutation('poll', (int) $poll->id, 'feed.poll.voted', (int) auth()->id(), [
                'option_id' => (int) $request->validated('option_id'),
            ]);
        }, 3);

        $activeMemberIdsSubquery = GroupUser::query()
            ->select('user_id')
            ->where('status', 1)
            ->where('group_id', $poll->group_id);

        $voteCounts = PollVote::where('poll_id', $poll->id)
            ->whereIn('user_id', $activeMemberIdsSubquery)
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
        abort_unless((int) $poll->group_id === (int) $group->id, 404);
        $this->authorize('update', $poll);
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
        abort_unless((int) $poll->group_id === (int) $group->id, 404);
        $this->authorize('delete', $poll);
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
        if (! (bool) config('group-chat.enabled', true)) {
            return;
        }

        if (strtolower((string) config('group-chat.transport', 'auto')) === 'polling') {
            return;
        }

        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch(static function () use ($event): void {
                try {
                    event($event);
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::warning('group_chat_broadcast_failed', [
                        'event' => get_class($event),
                        'message' => $exception->getMessage(),
                    ]);
                }
            })->afterResponse();
            return;
        }

        try {
            event($event);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('group_chat_broadcast_failed', [
                'event' => get_class($event),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Mark poll as read by current user
     */
    public function markAsRead(Poll $poll)
    {
        $this->authorize('view', $poll);
        $user = auth()->user();
        
        // Don't mark own polls as read
        if ($poll->created_by === $user->id) {
            return response()->json(['status' => 'ignored']);
        }

        $poll->markAsRead($user->id);

        $groupId = (int) ($poll->group_id ?? 0);
        if ($groupId > 0) {
            $this->dispatchGroupEvent(new GroupFeedUpdated($groupId, 'poll_read', [
                'poll_id' => (int) $poll->id,
                'read_count' => (int) $poll->read_count,
            ], (int) $user->id));
        }

        return response()->json([
            'status' => 'success',
            'read_count' => $poll->read_count,
        ]);
    }
}
