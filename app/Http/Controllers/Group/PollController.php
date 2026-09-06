<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Events\GroupPollUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StorePollRequest;
use App\Http\Requests\Group\VotePollRequest;
use App\Models\Delegation;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
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

        $inputs['expires_at'] = Carbon::now()->addDays((int) $inputs['expires_at'])->format('Y-m-d H:i:s');
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

        $this->awardNormalPollCreated($poll, auth()->user());
        $this->dispatchGroupEvent(new \App\Events\PollCreated($poll, $group, auth()->user()));

        $payload = [
            'poll_id' => (int) $poll->id,
            'html' => $this->renderPollHtml($poll, $group),
        ];

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $group->id, 'poll_created', $payload, (int) auth()->id()));

        $isElection = (int) $poll->main_type === 0;
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $isElection ? 'انتخابات با موفقیت ایجاد شد.' : 'نظرسنجی با موفقیت ایجاد شد.',
                'poll' => [
                    'id' => (int) $poll->id,
                    'html' => $payload['html'],
                ],
            ]);
        }

        return redirect()->back()->with('success', $isElection ? 'انتخابات با موفقیت ایجاد شد.' : 'نظرسنجی شما با موفقیت ارسال شد!');
    }

    public function vote(VotePollRequest $request, Poll $poll)
    {
        abort_if(! $poll->is_active || $poll->isExpired(), 422, 'This poll is not accepting votes.');

        $selectedOptionId = (int) $request->validated('option_id');
        $voteRemoved = DB::transaction(function () use ($poll, $selectedOptionId): bool {
            Poll::whereKey($poll->id)->lockForUpdate()->firstOrFail();
            $existing = $poll->votes()->where('user_id', auth()->id())->lockForUpdate()->first();

            if ($existing && (int) $existing->option_id === $selectedOptionId) {
                $existing->delete();
                app(GroupFeedService::class)->recordMutation('poll', (int) $poll->id, 'feed.poll.voted', (int) auth()->id(), [
                    'option_id' => null,
                    'removed' => true,
                ]);

                return true;
            }

            $existing?->delete();
            $poll->votes()->create([
                'user_id' => auth()->id(),
                'option_id' => $selectedOptionId,
            ]);
            app(GroupFeedService::class)->recordMutation('poll', (int) $poll->id, 'feed.poll.voted', (int) auth()->id(), [
                'option_id' => $selectedOptionId,
                'removed' => false,
            ]);

            return false;
        }, 3);

        if (! $voteRemoved) {
            $this->awardNormalPollParticipation($poll, auth()->user());
        }

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
            'user_option_id' => $voteRemoved ? null : $selectedOptionId,
            'total_votes' => (int) $totalVotes,
            'options' => $options,
        ];

        $broadcastPayload = $pollPayload;
        unset($broadcastPayload['user_option_id']);

        $this->dispatchGroupEvent(new GroupPollUpdated(
            (int) $poll->group_id,
            $broadcastPayload,
            (int) auth()->id()
        ));

        return response()->json([
            'status' => 'success',
            'vote_removed' => $voteRemoved,
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

        $nextType = (int) ($validated['type'] ?? $poll->type ?? 0);
        $poll->update([
            'question' => $validated['question'],
            'expires_at' => now()->addDays((int) ($validated['expires_at'] ?? 3)),
            'type' => $nextType,
            'skill_id' => $nextType === 1 ? ($validated['skill_id'] ?? null) : null,
        ]);
        $poll->forceFill(['edited_at' => now()])->save();
        $poll->refresh();

        $eventPayload = [
            'poll_id' => (int) $poll->id,
            'html' => $this->renderPollHtml($poll, $group),
        ];
        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $group->id, 'poll_updated', $eventPayload, (int) auth()->id()));

        $isElection = (int) $poll->main_type === 0;
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $isElection ? 'انتخابات با موفقیت ویرایش شد.' : 'نظرسنجی با موفقیت ویرایش شد.',
                'poll' => [
                    'id' => (int) $poll->id,
                    'html' => $eventPayload['html'],
                ],
            ]);
        }

        return redirect()->back()->with('success', $isElection ? 'انتخابات با موفقیت ویرایش شد.' : 'نظرسنجی با موفقیت ویرایش شد.');
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

    private function renderPollHtml(Poll $poll, Group $group): string
    {
        $poll->load(['options', 'votes', 'user', 'skill']);
        $poll->setAttribute('real_type', (int) ($poll->type ?? 0));

        $activeMemberIdsSubquery = GroupUser::query()
            ->select('user_id')
            ->where('status', 1)
            ->where('group_id', $group->id);

        $voteCounts = PollVote::query()
            ->where('poll_id', $poll->id)
            ->whereIn('user_id', $activeMemberIdsSubquery)
            ->selectRaw('option_id, COUNT(*) as votes_count')
            ->groupBy('option_id')
            ->pluck('votes_count', 'option_id');

        $pollOptionVotes = [
            (int) $poll->id => $voteCounts->map(fn ($count) => (int) $count)->all(),
        ];
        $pollTotals = [(int) $poll->id => (int) $voteCounts->sum()];

        $userVoteOptionId = PollVote::query()
            ->where('poll_id', $poll->id)
            ->where('user_id', auth()->id())
            ->value('option_id');
        $userVotesByPollId = $userVoteOptionId
            ? [(int) $poll->id => (int) $userVoteOptionId]
            : [];

        $delegationsByPollId = collect();
        if ((int) ($poll->type ?? 0) === 1) {
            $delegation = Delegation::query()
                ->where('poll_id', $poll->id)
                ->where('user_id', auth()->id())
                ->first();
            if ($delegation) {
                $delegationsByPollId = collect([(int) $poll->id => $delegation]);
            }
        }

        return view('groups.partials.poll', [
            'item' => $poll,
            'group' => $group,
            'userVote' => $userVoteOptionId,
            'pollTotals' => $pollTotals,
            'pollOptionVotes' => $pollOptionVotes,
            'userVotesByPollId' => $userVotesByPollId,
            'delegationsByPollId' => $delegationsByPollId,
        ])->render();
    }

    private function awardNormalPollCreated(Poll $poll, $user): void
    {
        if (! $user || (int) $poll->main_type !== 1) {
            return;
        }

        try {
            app(\App\Services\ReputationService::class)->applyAction(
                $user,
                'poll_created',
                ['poll_id' => (int) $poll->id, 'group_id' => (int) $poll->group_id],
                $poll->id,
                'groups.poll',
                'poll_created:poll:' . $poll->id . ':creator:' . $poll->created_by
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('poll_created_reputation_failed', [
                'poll_id' => (int) $poll->id,
                'user_id' => (int) $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function awardNormalPollParticipation(Poll $poll, $user): void
    {
        if (! $user || (int) $poll->main_type !== 1) {
            return;
        }

        try {
            app(\App\Services\ReputationService::class)->applyAction(
                $user,
                'poll_participated',
                ['poll_id' => (int) $poll->id, 'group_id' => (int) $poll->group_id],
                $poll->id,
                'groups.poll',
                'poll_participated:poll:' . $poll->id . ':user:' . $user->id
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('poll_participated_reputation_failed', [
                'poll_id' => (int) $poll->id,
                'user_id' => (int) $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function dispatchGroupEvent(object $event): void
    {
        app(\App\Services\GroupChat\GroupEventPublisher::class)->publish($event);
    }

    /**
     * Mark poll as read by current user
     */
    public function markAsRead(Poll $poll)
    {
        $this->authorize('view', $poll);
        $user = auth()->user();

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
