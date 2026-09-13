<?php

namespace App\Services\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Exceptions\ResidenceTransferLimitExceeded;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidenceService
{
    public function __construct(
        private readonly ResidenceTransferPolicy $transferPolicy,
        private readonly GovernanceResolver $governanceResolver,
        private readonly CanonicalGroupMembershipReconciler $groupMembershipReconciler,
    ) {
    }

    public function setInitialPrimaryResidence(User $user, Location $location, array $evidence): UserLocationRelationship
    {
        return DB::transaction(function () use ($user, $location, $evidence): UserLocationRelationship {
            $existing = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $this->reconcileCanonicalGroupsIfEnabled($user);

                return $existing;
            }

            $relationship = UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'relationship_type' => 'primary_residence',
                'started_at' => now(),
                'ended_at' => null,
                'evidence' => $evidence,
                'explicit_transfer' => false,
                'transfer_override' => false,
            ]);

            $this->reconcileCanonicalGroupsIfEnabled($user);

            return $relationship;
        });
    }

    public function setPendingResidenceIntent(
        User $user,
        LocationProposal $proposal,
        array $metadata = [],
    ): PendingResidenceIntent {
        if (! in_array($proposal->status, [
            LocationProposalStatus::Pending,
            LocationProposalStatus::ReadyForReview,
            LocationProposalStatus::NeedsEvidence,
        ], true)) {
            throw ValidationException::withMessages([
                'location_proposal_id' => 'این پیشنهاد مکان دیگر در وضعیت قابل انتخاب نیست.',
            ]);
        }

        return DB::transaction(function () use ($user, $proposal, $metadata): PendingResidenceIntent {
            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'ابتدا باید یک مکان تأییدشده به‌عنوان مبنای محل سکونت مشخص شود.',
                ]);
            }

            if ((int) $proposal->parent_location_id !== (int) $current->location_id) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'پیشنهاد مکان باید ادامهٔ همان مسیر محل سکونت تأییدشده باشد.',
                ]);
            }

            $at = now();
            $this->cancelPendingIntentRows($user, 'replaced_by_new_pending_residence', $at);

            return PendingResidenceIntent::query()->create([
                'user_id' => $user->id,
                'anchor_relationship_id' => $current->id,
                'location_proposal_id' => $proposal->id,
                'status' => 'pending',
                'selected_at' => $at,
                'metadata' => $metadata,
            ]);
        });
    }

    public function clearPendingResidenceIntent(User $user, string $reason): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $this->cancelPendingIntentRows($user, $reason, now());
        });
    }

    public function resolvePendingResidenceIntents(LocationProposal $proposal, Location $resolvedLocation): int
    {
        return DB::transaction(function () use ($proposal, $resolvedLocation): int {
            $resolvedCount = 0;
            $at = now();

            $intents = PendingResidenceIntent::query()
                ->where('location_proposal_id', $proposal->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($intents as $intent) {
                $anchor = UserLocationRelationship::query()
                    ->whereKey($intent->anchor_relationship_id)
                    ->where('user_id', $intent->user_id)
                    ->where('relationship_type', 'primary_residence')
                    ->lockForUpdate()
                    ->first();

                $current = UserLocationRelationship::query()
                    ->where('user_id', $intent->user_id)
                    ->where('relationship_type', 'primary_residence')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if ($anchor === null || $current === null || (int) $current->id !== (int) $anchor->id) {
                    $this->cancelIntent($intent, 'stale_anchor', $at);
                    continue;
                }

                if ((int) $current->location_id !== (int) $resolvedLocation->id) {
                    $current->forceFill(['ended_at' => $at])->save();

                    UserLocationRelationship::query()->create([
                        'user_id' => $intent->user_id,
                        'location_id' => $resolvedLocation->id,
                        'relationship_type' => 'primary_residence',
                        'started_at' => $at,
                        'ended_at' => null,
                        'evidence' => [
                            'source' => 'location_proposal_resolution',
                            'proposal_id' => $proposal->id,
                        ],
                        'explicit_transfer' => false,
                        'transfer_override' => false,
                        'changed_by_user_id' => $proposal->reviewed_by_user_id,
                        'change_reason' => 'location_proposal_resolution',
                        'metadata' => [
                            'pending_residence_intent_id' => $intent->id,
                            'refined_from_relationship_id' => $anchor->id,
                        ],
                    ]);
                }

                $intent->forceFill([
                    'status' => 'resolved',
                    'resolved_location_id' => $resolvedLocation->id,
                    'resolved_at' => $at,
                ])->save();

                $resolvedCount++;
                $this->reconcileCanonicalGroupsIfEnabled(User::query()->findOrFail($intent->user_id));
            }

            return $resolvedCount;
        });
    }

    public function transferPrimaryResidence(
        User $user,
        Location $to,
        User $actor,
        string $reason,
        bool $override = false,
    ): UserLocationRelationship {
        $at = now();

        if (! $override && ! $this->transferPolicy->allowsExplicitTransfer($user, $at)) {
            throw new ResidenceTransferLimitExceeded('The rolling primary-residence transfer limit has been reached.');
        }

        return DB::transaction(function () use ($user, $to, $actor, $reason, $override, $at): UserLocationRelationship {
            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->forceFill(['ended_at' => $at])->save();
            }

            $this->cancelPendingIntentRows($user, 'primary_residence_transferred', $at);

            $relationship = UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $to->id,
                'relationship_type' => 'primary_residence',
                'started_at' => $at,
                'ended_at' => null,
                'evidence' => [],
                'explicit_transfer' => true,
                'transfer_override' => $override,
                'changed_by_user_id' => $actor->id,
                'change_reason' => $reason,
            ]);

            $this->reconcileCanonicalGroupsIfEnabled($user);

            return $relationship;
        });
    }

    public function currentPrimaryResidence(User $user): ?UserLocationRelationship
    {
        return UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->with('location')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    public function officialGovernanceAreasFor(User $user): Collection
    {
        $primaryResidence = $this->currentPrimaryResidence($user);

        if ($primaryResidence === null || $primaryResidence->location === null) {
            return collect();
        }

        return $this->governanceResolver->officialAreasForResidence($primaryResidence->location);
    }

    private function cancelPendingIntentRows(User $user, string $reason, $at): void
    {
        $intents = PendingResidenceIntent::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->get();

        foreach ($intents as $intent) {
            $this->cancelIntent($intent, $reason, $at);
        }
    }

    private function cancelIntent(PendingResidenceIntent $intent, string $reason, $at): void
    {
        $metadata = $intent->metadata ?? [];
        $metadata['cancellation_reason'] = $reason;

        $intent->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => $at,
            'metadata' => $metadata,
        ])->save();
    }

    private function reconcileCanonicalGroupsIfEnabled(User $user): void
    {
        if ((bool) config('location-governance.groups_enabled', false)) {
            $this->groupMembershipReconciler->reconcile($user);
        }
    }
}
