<?php

namespace App\Services\Elections;

use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Support\Collection;

class InternalElectionCurrentItemAdapter
{
    public function itemsFor(User $user): Collection
    {
        $polls = Poll::query()
            ->where('main_type', 0)
            ->where('is_active', true)
            ->whereHas('group.users', fn ($query) => $query->whereKey($user->id))
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('group')
            ->orderByRaw('expires_at is null, expires_at asc')
            ->orderByDesc('id')
            ->get();

        if ($polls->isEmpty()) {
            return collect();
        }

        $votedPollIds = PollVote::query()
            ->where('user_id', $user->id)
            ->whereIn('poll_id', $polls->pluck('id'))
            ->pluck('poll_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return $polls->map(function (Poll $poll) use ($user, $votedPollIds): array {
            $eligible = $this->previewVoteEligibility($user, $poll);
            $hasVoted = $votedPollIds->has((int) $poll->id);
            $canVoteNow = (bool) $poll->is_active && ! $poll->isExpired() && $eligible;
            $targetUrl = route('groups.chat', $poll->group) . '#poll-' . $poll->id;

            return [
                'source_type' => 'internal',
                'source_id' => (int) $poll->id,
                'group_id' => (int) $poll->group_id,
                'group_name' => (string) ($poll->group?->name ?? 'گروه'),
                'title' => (string) $poll->question,
                'status_key' => 'open',
                'status_label' => 'در حال رأی‌گیری',
                'is_current' => true,
                'eligible' => $eligible,
                'eligibility_label' => $eligible ? 'واجد شرایط رأی دادن' : 'در این انتخابات حق رأی ندارید',
                'has_voted' => $hasVoted,
                'can_vote_now' => $canVoteNow,
                'can_edit_vote' => $canVoteNow && $hasVoted,
                'starts_at' => $poll->created_at,
                'ends_at' => $poll->expires_at,
                'deadline_label' => $poll->expires_at ? $poll->expires_at->diffForHumans() : 'بدون مهلت ثبت‌شده',
                'primary_action' => [
                    'label' => $canVoteNow ? ($hasVoted ? 'ویرایش رأی' : 'ثبت رأی') : 'مشاهده انتخابات',
                    'url' => $targetUrl,
                    'kind' => $canVoteNow ? 'vote' : 'details',
                ],
                'secondary_action' => null,
                'priority_bucket' => $canVoteNow && ! $hasVoted ? 'action_required' : 'related',
            ];
        });
    }

    /**
     * Read-only equivalent of PollPolicy::vote() + GroupPolicy::participate().
     *
     * The policy path may restore an expired temporary role as a maintenance
     * side effect. The Current Elections Center must not mutate state, so this
     * reader computes the same effective role in memory without saving it.
     */
    private function previewVoteEligibility(User $user, Poll $poll): bool
    {
        $membership = GroupUser::query()
            ->where('group_id', $poll->group_id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('expired')->orWhere('expired', 0)->orWhere('expired', '>', now());
            })
            ->first();

        if ($membership === null) {
            return false;
        }

        $role = (int) $membership->role;
        if (
            $membership->role_override_active
            && $membership->role_override_expires_at
            && $membership->role_override_expires_at->isPast()
            && $membership->role_override_original_role !== null
        ) {
            $role = (int) $membership->role_override_original_role;
        }

        if ($role === 0) {
            return false;
        }

        return (bool) $poll->group?->is_open
            || in_array($role, [2, 3], true)
            || (bool) $membership->session_write_allowed;
    }
}
