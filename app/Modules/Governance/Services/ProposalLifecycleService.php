<?php

namespace App\Modules\Governance\Services;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\AgendaItem;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\ProposalSupport;
use App\Modules\Governance\Models\Resolution;
use Illuminate\Support\Facades\DB;

class ProposalLifecycleService
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_PUBLIC_PROJECT = 'public_project';
    public const TYPE_PUBLIC_EXPENDITURE = 'public_expenditure';
    public const TYPE_GUILD_FUND = 'guild_fund';
    public const TYPE_POLICY = 'policy';

    public function create(Group $group, User $author, array $data): Proposal
    {
        $this->assertActiveMember($group, $author);

        $type = $data['type'] ?? self::TYPE_GENERAL;
        if (! in_array($type, [self::TYPE_GENERAL, self::TYPE_PUBLIC_PROJECT, self::TYPE_PUBLIC_EXPENDITURE, self::TYPE_GUILD_FUND, self::TYPE_POLICY], true)) {
            throw new \InvalidArgumentException('Unsupported governance proposal type.');
        }

        return Proposal::create([
            'group_id' => $group->id,
            'created_by' => $author->id,
            'type' => $type,
            'title' => (string) $data['title'],
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'support_threshold' => $data['support_threshold'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'status' => 'draft',
        ]);
    }

    public function submitForDiscussion(Proposal $proposal, User $actor): Proposal
    {
        $this->assertActiveMember($proposal->group, $actor);
        if ((int) $proposal->created_by !== (int) $actor->id && ! $this->isManagerOrInspector($proposal->group, $actor)) {
            throw new \RuntimeException('Only the proposal creator or a group manager/inspector may submit it.');
        }
        if ($proposal->status !== 'draft') {
            throw new \RuntimeException('Only draft proposals can enter discussion.');
        }

        $proposal->update(['status' => 'under_discussion', 'submitted_at' => now()]);
        return $proposal->fresh();
    }

    public function support(Proposal $proposal, User $member, string $source = 'explicit_endorsement', ?string $sourceReference = null, array $metadata = []): ProposalSupport
    {
        $this->assertActiveMember($proposal->group, $member);
        if (! in_array($proposal->status, ['under_discussion', 'supported'], true)) {
            throw new \RuntimeException('Proposal is not open for community support.');
        }

        return DB::transaction(function () use ($proposal, $member, $source, $sourceReference, $metadata) {
            $support = ProposalSupport::firstOrCreate(
                ['proposal_id' => $proposal->id, 'user_id' => $member->id],
                ['source' => $source, 'source_reference' => $sourceReference, 'metadata' => $metadata]
            );

            $count = ProposalSupport::where('proposal_id', $proposal->id)->count();
            $proposal->support_count = $count;
            if ($proposal->support_threshold !== null && $count >= (int) $proposal->support_threshold) {
                $proposal->status = 'supported';
                $proposal->supported_at ??= now();
            }
            $proposal->save();

            return $support;
        }, 3);
    }

    public function placeOnAgenda(Proposal $proposal, User $actor, bool $professionalReferralRequired = false, ?string $referralNotes = null, $scheduledAt = null): AgendaItem
    {
        $this->assertManagerOrInspector($proposal->group, $actor);
        if ($proposal->status !== 'supported') {
            throw new \RuntimeException('Only supported proposals can be placed on the assembly agenda.');
        }

        return DB::transaction(function () use ($proposal, $actor, $professionalReferralRequired, $referralNotes, $scheduledAt) {
            $item = AgendaItem::create([
                'proposal_id' => $proposal->id,
                'group_id' => $proposal->group_id,
                'added_by' => $actor->id,
                'status' => $professionalReferralRequired ? 'referral_pending' : 'scheduled',
                'professional_referral_required' => $professionalReferralRequired,
                'referral_notes' => $referralNotes,
                'scheduled_at' => $scheduledAt,
            ]);

            $proposal->update([
                'status' => $professionalReferralRequired ? 'referred' : 'agenda',
                'agenda_at' => now(),
            ]);

            return $item;
        }, 3);
    }

    public function markAssessable(Proposal $proposal, User $actor, array $assessmentMetadata = []): Proposal
    {
        $this->assertManagerOrInspector($proposal->group, $actor);
        if (! in_array($proposal->status, ['agenda', 'referred'], true)) {
            throw new \RuntimeException('Proposal must be on the agenda or in professional referral before assessment can complete.');
        }

        DB::transaction(function () use ($proposal, $assessmentMetadata) {
            $agenda = $proposal->agendaItems()->latest('id')->lockForUpdate()->first();
            if (! $agenda) {
                throw new \RuntimeException('Agenda item not found for proposal.');
            }

            $agenda->update([
                'status' => 'ready_for_vote',
                'metadata' => array_merge((array) ($agenda->metadata ?? []), ['assessment' => $assessmentMetadata]),
            ]);
            $proposal->update(['status' => 'ready_for_vote']);
        }, 3);

        return $proposal->fresh();
    }

    public function openVote(Proposal $proposal, Poll $poll, User $actor): Proposal
    {
        $this->assertManagerOrInspector($proposal->group, $actor);
        if ($proposal->status !== 'ready_for_vote') {
            throw new \RuntimeException('Proposal is not ready for assembly vote.');
        }
        if ((int) $poll->group_id !== (int) $proposal->group_id) {
            throw new \RuntimeException('Poll must belong to the same assembly as the proposal.');
        }

        $metadata = (array) ($proposal->metadata ?? []);
        $metadata['decision_poll_id'] = $poll->id;
        $proposal->update(['status' => 'voting', 'metadata' => $metadata]);

        return $proposal->fresh();
    }

    /**
     * Record the formal assembly decision. Financial effects are only recorded
     * as `pending_bridge`; this service never creates projects, obligations or
     * transfers money directly.
     */
    public function recordDecision(Proposal $proposal, Poll $poll, User $actor, array $result, array $financialEffect = []): Resolution
    {
        $this->assertManagerOrInspector($proposal->group, $actor);
        if ($proposal->status !== 'voting') {
            throw new \RuntimeException('Only proposals currently in voting can become formal resolutions.');
        }
        if ((int) $poll->group_id !== (int) $proposal->group_id) {
            throw new \RuntimeException('Decision poll does not belong to the proposal assembly.');
        }
        if ($poll->is_active && ! $poll->isExpired()) {
            throw new \RuntimeException('Decision poll must be closed or expired before recording a resolution.');
        }

        $eligible = max(0, (int) ($result['eligible_voter_count'] ?? 0));
        $cast = max(0, (int) ($result['votes_cast'] ?? 0));
        $for = max(0, (int) ($result['votes_for'] ?? 0));
        $against = max(0, (int) ($result['votes_against'] ?? 0));
        $abstain = max(0, (int) ($result['votes_abstain'] ?? 0));
        $quorumRequired = (float) ($result['quorum_required_percent'] ?? 0);
        $approvalRequired = (float) ($result['approval_required_percent'] ?? 50);

        if ($cast > $eligible || ($for + $against + $abstain) > $cast) {
            throw new \InvalidArgumentException('Vote totals are inconsistent.');
        }

        $quorumPercent = $eligible > 0 ? ($cast / $eligible) * 100 : 0;
        $decisiveVotes = $for + $against;
        $approvalPercent = $decisiveVotes > 0 ? ($for / $decisiveVotes) * 100 : 0;
        $adopted = $quorumPercent >= $quorumRequired && $approvalPercent >= $approvalRequired;

        return DB::transaction(function () use ($proposal, $poll, $actor, $eligible, $cast, $for, $against, $abstain, $quorumRequired, $approvalRequired, $quorumPercent, $approvalPercent, $adopted, $financialEffect) {
            $resolution = Resolution::create([
                'proposal_id' => $proposal->id,
                'group_id' => $proposal->group_id,
                'poll_id' => $poll->id,
                'adopted_by' => $actor->id,
                'type' => $proposal->type,
                'status' => $adopted ? 'adopted' : 'rejected',
                'effect_status' => $adopted && ! empty($financialEffect) ? 'pending_bridge' : 'none',
                'quorum_required_percent' => $quorumRequired,
                'approval_required_percent' => $approvalRequired,
                'eligible_voter_count' => $eligible,
                'votes_cast' => $cast,
                'votes_for' => $for,
                'votes_against' => $against,
                'votes_abstain' => $abstain,
                'financial_effect' => $financialEffect ?: null,
                'metadata' => [
                    'quorum_percent' => round($quorumPercent, 4),
                    'approval_percent' => round($approvalPercent, 4),
                    'economic_effect_executed' => false,
                ],
                'adopted_at' => $adopted ? now() : null,
                'effective_at' => $adopted ? now() : null,
            ]);

            $proposal->update(['status' => $adopted ? 'approved' : 'rejected']);
            $proposal->agendaItems()->latest('id')->first()?->update(['status' => 'completed']);

            return $resolution;
        }, 3);
    }

    private function assertActiveMember(Group $group, User $user): GroupUser
    {
        $membership = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->first();

        if (! $membership) {
            throw new \RuntimeException('Only active assembly members may participate in proposal governance.');
        }

        return $membership;
    }

    private function assertManagerOrInspector(Group $group, User $user): GroupUser
    {
        $membership = $this->assertActiveMember($group, $user);
        if (! in_array((int) $membership->role, [2, 3], true)) {
            throw new \RuntimeException('Only group managers or inspectors may perform this governance transition.');
        }

        return $membership;
    }

    private function isManagerOrInspector(Group $group, User $user): bool
    {
        $membership = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->first();

        return $membership && in_array((int) $membership->role, [2, 3], true);
    }
}
