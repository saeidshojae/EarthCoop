<?php

namespace App\Modules\Governance\Services;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Governance\Models\AgendaItem;
use App\Modules\Governance\Models\ProposalReferral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfessionalReferralService
{
    public function refer(AgendaItem $agendaItem, Group $targetGroup, User $actor, ?string $requestNotes = null): ProposalReferral
    {
        $this->assertManagerOrInspector($agendaItem->group, $actor);

        if (! $agendaItem->professional_referral_required) {
            throw new \RuntimeException('This agenda item does not require professional referral.');
        }
        if (! in_array($agendaItem->status, ['referral_pending', 'referral_completed'], true)) {
            throw new \RuntimeException('Agenda item is not in a referral-compatible state.');
        }
        if ((int) $targetGroup->id === (int) $agendaItem->group_id) {
            throw new \RuntimeException('Professional referral must target another assembly.');
        }

        return DB::transaction(function () use ($agendaItem, $targetGroup, $actor, $requestNotes) {
            $existing = ProposalReferral::where('agenda_item_id', $agendaItem->id)
                ->whereIn('status', ['pending', 'in_review'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $referral = ProposalReferral::create([
                'proposal_id' => $agendaItem->proposal_id,
                'agenda_item_id' => $agendaItem->id,
                'source_group_id' => $agendaItem->group_id,
                'target_group_id' => $targetGroup->id,
                'referred_by' => $actor->id,
                'status' => 'pending',
                'request_notes' => $requestNotes,
            ]);

            $agendaItem->update(['status' => 'referral_pending']);
            return $referral;
        }, 3);
    }

    public function accept(ProposalReferral $referral, User $actor): ProposalReferral
    {
        $this->assertManagerOrInspector($referral->targetGroup, $actor);
        if ($referral->status !== 'pending') {
            throw new \RuntimeException('Only pending referrals can be accepted.');
        }

        $referral->update([
            'status' => 'in_review',
            'accepted_by' => $actor->id,
            'accepted_at' => now(),
        ]);

        return $referral->fresh();
    }

    public function complete(ProposalReferral $referral, User $actor, array $assessment, ?string $responseNotes = null): ProposalReferral
    {
        $this->assertManagerOrInspector($referral->targetGroup, $actor);
        if ($referral->status !== 'in_review') {
            throw new \RuntimeException('Referral must be accepted before professional review can complete.');
        }

        $completed = DB::transaction(function () use ($referral, $actor, $assessment, $responseNotes) {
            $referral->update([
                'status' => 'completed',
                'completed_by' => $actor->id,
                'assessment' => $assessment,
                'response_notes' => $responseNotes,
                'completed_at' => now(),
            ]);

            $referral->agendaItem()->update(['status' => 'referral_completed']);
            return $referral->fresh();
        }, 3);

        $this->awardCompletedReferralParticipation($completed, $actor);

        return $completed;
    }

    private function awardCompletedReferralParticipation(ProposalReferral $completed, User $actor): void
    {
        try {
            app(\App\Services\ReputationService::class)->applyAction(
                $actor,
                'professional_referral_completed',
                [
                    'referral_id' => (int) $completed->id,
                    'proposal_id' => (int) $completed->proposal_id,
                    'agenda_item_id' => (int) $completed->agenda_item_id,
                    'source_group_id' => (int) $completed->source_group_id,
                    'target_group_id' => (int) $completed->target_group_id,
                ],
                $completed->id,
                'governance.professional_referral',
                'professional_referral_completed:referral:' . $completed->id
            );
        } catch (\Throwable $exception) {
            Log::warning('professional_referral_reputation_failed', [
                'referral_id' => (int) $completed->id,
                'user_id' => (int) $actor->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function assertManagerOrInspector(Group $group, User $user): GroupUser
    {
        $membership = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->first();

        if (! $membership || ! in_array((int) $membership->role, [2, 3], true)) {
            throw new \RuntimeException('Only an active manager or inspector of the assembly may perform this referral action.');
        }

        return $membership;
    }
}
