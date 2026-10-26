<?php

namespace App\Services\GroupChat;

use App\Models\Delegation;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Support\Collection;

class PollVoteTallyService
{
    /**
     * Calculate poll results using the ballot of each active voter plus one
     * additional weight for every valid active delegation received by that voter.
     *
     * A delegated user's old/direct ballot is excluded. Delegations only carry
     * weight after the selected expert has cast a ballot for that poll.
     *
     * @return array{options: array<int, array<int, int>>, totals: array<int, int>}
     */
    public function countOptionsForPolls(Collection $polls): array
    {
        $polls = $polls->keyBy(fn (Poll $poll) => (int) $poll->id);
        $result = ['options' => [], 'totals' => []];

        foreach ($polls->groupBy(fn (Poll $poll) => (int) $poll->group_id) as $groupId => $groupPolls) {
            $pollIds = $groupPolls->keys()->map(fn ($id) => (int) $id)->values();
            $activeMemberIds = GroupUser::query()
                ->where('group_id', (int) $groupId)
                ->where('status', 1)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($pollIds->isEmpty() || $activeMemberIds->isEmpty()) {
                continue;
            }

            $specializedPolls = $groupPolls->filter(fn (Poll $poll) => $this->isSpecialized($poll));
            $validDelegations = $this->validDelegationsForPolls($specializedPolls, $activeMemberIds);
            $delegatedUserIdsByPoll = [];
            $incomingDelegationCounts = [];

            foreach ($validDelegations as $delegation) {
                $pollId = (int) $delegation->poll_id;
                $userId = (int) $delegation->user_id;
                $expertId = (int) $delegation->expert_id;
                $delegatedUserIdsByPoll[$pollId][$userId] = true;
                $incomingDelegationCounts[$pollId][$expertId] = ($incomingDelegationCounts[$pollId][$expertId] ?? 0) + 1;
            }

            // A voter should have one current ballot per poll. Keep the newest
            // row defensively if legacy data contains duplicate ballots.
            $votes = PollVote::query()
                ->whereIn('poll_id', $pollIds)
                ->whereIn('user_id', $activeMemberIds)
                ->orderByDesc('id')
                ->get(['id', 'poll_id', 'user_id', 'option_id'])
                ->unique(fn (PollVote $vote) => (int) $vote->poll_id . ':' . (int) $vote->user_id)
                ->values();

            foreach ($votes as $vote) {
                $pollId = (int) $vote->poll_id;
                $userId = (int) $vote->user_id;
                $optionId = (int) $vote->option_id;
                $poll = $groupPolls->get($pollId);

                if (! $poll) {
                    continue;
                }

                // Delegating transfers the ballot: the delegator's own ballot
                // must never be counted in addition to the expert's weighted vote.
                if ($this->isSpecialized($poll) && isset($delegatedUserIdsByPoll[$pollId][$userId])) {
                    continue;
                }

                $weight = 1;
                if ($this->isSpecialized($poll)) {
                    $weight += (int) ($incomingDelegationCounts[$pollId][$userId] ?? 0);
                }

                $result['options'][$pollId][$optionId] = ($result['options'][$pollId][$optionId] ?? 0) + $weight;
                $result['totals'][$pollId] = ($result['totals'][$pollId] ?? 0) + $weight;
            }
        }

        return $result;
    }

    /**
     * Return only currently valid outgoing delegations for rendering card state.
     * Stale or ineligible delegation rows must not disable a member's ballot.
     */
    public function activeDelegationsByUserForPolls(Collection $polls, int $userId): Collection
    {
        $polls = $polls->keyBy(fn (Poll $poll) => (int) $poll->id);
        $result = collect();

        foreach ($polls->groupBy(fn (Poll $poll) => (int) $poll->group_id) as $groupId => $groupPolls) {
            $activeMemberIds = GroupUser::query()
                ->where('group_id', (int) $groupId)
                ->where('status', 1)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $specializedPolls = $groupPolls->filter(fn (Poll $poll) => $this->isSpecialized($poll));
            $result = $result->merge(
                $this->validDelegationsForPolls($specializedPolls, $activeMemberIds)
                    ->filter(fn (Delegation $delegation) => (int) $delegation->user_id === $userId)
            );
        }

        return $result->keyBy('poll_id');
    }

    /**
     * A server-side guard: delegated members cannot submit a separate ballot.
     */
    public function hasActiveDelegation(Poll $poll, int $userId): bool
    {
        if (! $this->isSpecialized($poll)) {
            return false;
        }

        $activeMemberIds = GroupUser::query()
            ->where('group_id', (int) $poll->group_id)
            ->where('status', 1)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $this->validDelegationsForPolls(collect([(int) $poll->id => $poll]), $activeMemberIds)
            ->contains(fn (Delegation $delegation) => (int) $delegation->user_id === $userId);
    }

    /**
     * Delegation is intentionally one-level: an expert with an outgoing
     * delegation cannot receive another member's delegation for this poll.
     */
    public function hasIncomingActiveDelegations(Poll $poll, int $expertId): bool
    {
        if (! $this->isSpecialized($poll)) {
            return false;
        }

        $activeMemberIds = GroupUser::query()
            ->where('group_id', (int) $poll->group_id)
            ->where('status', 1)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $this->validDelegationsForPolls(collect([(int) $poll->id => $poll]), $activeMemberIds)
            ->contains(fn (Delegation $delegation) => (int) $delegation->expert_id === $expertId);
    }

    /**
     * Build the result payload shared by vote responses and real-time updates.
     */
    public function resultPayload(Poll $poll): array
    {
        $tally = $this->countOptionsForPolls(collect([(int) $poll->id => $poll]));
        $counts = $tally['options'][(int) $poll->id] ?? [];
        $total = (int) ($tally['totals'][(int) $poll->id] ?? 0);

        $options = $poll->options()
            ->select('id', 'text')
            ->get()
            ->map(function ($option) use ($counts, $total) {
                $count = (int) ($counts[(int) $option->id] ?? 0);

                return [
                    'id' => (int) $option->id,
                    'text' => (string) $option->text,
                    'count' => $count,
                    'percent' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $poll->id,
            'total_votes' => $total,
            'options' => $options,
        ];
    }

    private function validDelegationsForPolls(Collection $polls, Collection $activeMemberIds): Collection
    {
        if ($polls->isEmpty() || $activeMemberIds->isEmpty()) {
            return collect();
        }

        $pollIds = $polls->keys()->map(fn ($id) => (int) $id)->values();
        $skillIds = $polls->pluck('skill_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($skillIds->isEmpty()) {
            return collect();
        }

        $delegations = Delegation::query()
            ->whereIn('poll_id', $pollIds)
            ->whereIn('user_id', $activeMemberIds)
            ->whereIn('expert_id', $activeMemberIds)
            ->whereColumn('user_id', '<>', 'expert_id')
            ->orderByDesc('id')
            ->get(['id', 'poll_id', 'user_id', 'expert_id'])
            // Tolerate duplicate active legacy rows deterministically: latest row wins.
            ->unique(fn (Delegation $delegation) => (int) $delegation->poll_id . ':' . (int) $delegation->user_id)
            ->values();

        if ($delegations->isEmpty()) {
            return collect();
        }

        $expertIds = $delegations->pluck('expert_id')->map(fn ($id) => (int) $id)->unique()->values();
        $experts = User::query()
            ->whereIn('id', $expertIds)
            ->with(['experiences' => fn ($query) => $query
                ->whereIn('experience_fields.id', $skillIds)
                ->select('experience_fields.id')])
            ->get(['id'])
            ->keyBy('id');

        $qualifiedDelegations = $delegations->filter(function (Delegation $delegation) use ($polls, $experts): bool {
            $poll = $polls->get((int) $delegation->poll_id);
            $expert = $experts->get((int) $delegation->expert_id);

            return $poll instanceof Poll
                && $this->isSpecialized($poll)
                && $expert !== null
                && $expert->experiences->contains(fn ($experience) => (int) $experience->id === (int) $poll->skill_id);
        })->values();

        // Fail closed for legacy chains/cycles too. Only a terminal expert who
        // can cast a direct ballot receives delegated weight; otherwise the
        // delegation is not treated as active and the delegator may vote directly.
        $outgoingDelegatorsByPoll = $qualifiedDelegations
            ->groupBy(fn (Delegation $delegation) => (int) $delegation->poll_id)
            ->map(fn (Collection $rows) => $rows->pluck('user_id')->map(fn ($id) => (int) $id)->flip());

        return $qualifiedDelegations->reject(function (Delegation $delegation) use ($outgoingDelegatorsByPoll): bool {
            $outgoing = $outgoingDelegatorsByPoll->get((int) $delegation->poll_id, collect());

            return $outgoing->has((int) $delegation->expert_id);
        })->values();
    }

    private function isSpecialized(Poll $poll): bool
    {
        $type = $poll->real_type ?? $poll->type ?? 0;

        return (int) $type === 1 && ! empty($poll->skill_id);
    }
}
