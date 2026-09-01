<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Events\Elections\ElectionAppointmentApplied;
use App\Events\Elections\ElectionAppointmentRevoked;
use App\Events\Elections\ElectionRepresentationActivated;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionRepresentationAssignment;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ElectionAppointmentService
{
    private const ACTIVE_MEMBER_ROLE = 1;
    private const MANAGER_ROLE = 2;
    private const INSPECTOR_ROLE = 3;

    public function __construct(
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionPolicyResolver $policyResolver,
        private readonly ElectionGroupHierarchyResolver $hierarchy,
    ) {}

    public function process(Election $election): array
    {
        $election = Election::query()->findOrFail($election->id);
        $status = $this->lifecycle->currentStatus($election);
        $accepted = ElectionResponsibilityOffer::query()
            ->where('election_id', $election->id)
            ->where('status', ElectionResponsibilityOfferStatus::Accepted->value)
            ->exists();

        if ($status === ElectionLifecycleStatus::AwaitingAcceptance && $accepted) {
            $election = $this->lifecycle->transition(
                $election,
                ElectionLifecycleStatus::Appointing,
                'accepted_responsibility_offer_ready_for_appointment',
                'election_appointment_service',
            );
            $status = ElectionLifecycleStatus::Appointing;
        }

        if (! in_array($status, [ElectionLifecycleStatus::AwaitingAcceptance, ElectionLifecycleStatus::Appointing], true)) {
            throw new RuntimeException("Election [{$election->id}] is not in an appointment-capable state.");
        }

        if ($status === ElectionLifecycleStatus::Appointing) {
            $offers = ElectionResponsibilityOffer::query()
                ->where('election_id', $election->id)
                ->where('status', ElectionResponsibilityOfferStatus::Accepted->value)
                ->orderBy('position')
                ->orderBy('ranking_position')
                ->get();

            foreach ($offers as $offer) {
                $this->appoint($offer);
            }

            $election = Election::query()->findOrFail($election->id);
            if ($this->isFilled($election)) {
                $election = $this->lifecycle->transition(
                    $election,
                    ElectionLifecycleStatus::Filled,
                    'all_required_responsibility_seats_appointed',
                    'election_appointment_service',
                );
            }
        }

        return $this->summary($election->refresh());
    }

    public function appoint(ElectionResponsibilityOffer $offer): ElectionAppointment
    {
        return DB::transaction(function () use ($offer): ElectionAppointment {
            $lockedOffer = ElectionResponsibilityOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->status !== ElectionResponsibilityOfferStatus::Accepted) {
                throw new RuntimeException("Responsibility offer [{$lockedOffer->id}] is not accepted.");
            }

            $election = Election::query()->lockForUpdate()->findOrFail($lockedOffer->election_id);
            $source = Group::query()->lockForUpdate()->findOrFail($election->group_id);
            $user = User::query()->findOrFail($lockedOffer->candidate_user_id);
            $position = ElectionPosition::from($lockedOffer->position);
            $groupRole = $this->groupRole($position);

            $this->assertSourceMembershipStillValid($source, $user);
            $this->assertNoSameGroupPositionConflict($source, $user, $position);

            $existing = ElectionAppointment::query()
                ->where('responsibility_offer_id', $lockedOffer->id)
                ->where('group_id', $source->id)
                ->where('position', $position->value)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $direct = ElectionAppointment::create([
                'election_id' => $election->id,
                'responsibility_offer_id' => $lockedOffer->id,
                'user_id' => $user->id,
                'group_id' => $source->id,
                'position' => $position->value,
                'group_role' => $groupRole,
                'appointment_kind' => 'direct',
                'status' => 'active',
                'appointed_at' => now(),
                'actor' => 'election_appointment_service',
                'reason' => 'accepted_ranked_responsibility_offer',
                'metadata' => [
                    'ranking_position' => (int) $lockedOffer->ranking_position,
                    'contract_version_id' => (int) $lockedOffer->contract_version_id,
                ],
            ]);
            ElectionAppointmentApplied::dispatch($direct);

            $this->applyResponsibilityMembership($source, $user, $groupRole);
            $this->supersedeLowerIndependentAppointments($direct, $source, $user, $position);

            $currentAppointment = $direct;
            $currentGroup = $source;
            $compressionChain = $this->hierarchy->compressionChain($source, $user);

            foreach ($compressionChain as $inheritedGroup) {
                $inherited = ElectionAppointment::query()->firstOrCreate(
                    [
                        'responsibility_offer_id' => $lockedOffer->id,
                        'group_id' => $inheritedGroup->id,
                        'position' => $position->value,
                    ],
                    [
                        'election_id' => $election->id,
                        'user_id' => $user->id,
                        'group_role' => $groupRole,
                        'appointment_kind' => 'inherited',
                        'source_appointment_id' => $currentAppointment->id,
                        'status' => 'active',
                        'appointed_at' => now(),
                        'actor' => 'election_appointment_service',
                        'reason' => 'sole_structural_constituency_electoral_compression',
                        'metadata' => [
                            'inherited_from_group_id' => $currentGroup->id,
                            'structural_constituency_count' => 1,
                        ],
                    ],
                );
                if ($inherited->wasRecentlyCreated) {
                    ElectionAppointmentApplied::dispatch($inherited);
                }

                $this->applyResponsibilityMembership($inheritedGroup, $user, $groupRole);
                $currentAppointment = $inherited;
                $currentGroup = $inheritedGroup;
            }

            $representedGroup = $this->hierarchy->nextElectoralParent($source, $user);
            if ($representedGroup !== null) {
                $this->applyRepresentativeMembership($representedGroup, $user);

                $assignment = ElectionRepresentationAssignment::query()->firstOrCreate(
                    ['appointment_id' => $currentAppointment->id],
                    [
                        'user_id' => $user->id,
                        'source_group_id' => $currentGroup->id,
                        'represented_group_id' => $representedGroup->id,
                        'status' => 'active',
                        'activated_at' => now(),
                        'reason' => 'elected_responsibility_represents_effective_constituency_upward',
                        'metadata' => [
                            'source_appointment_kind' => $currentAppointment->appointment_kind,
                            'electoral_compression_depth' => count($compressionChain),
                        ],
                    ],
                );
                if ($assignment->wasRecentlyCreated) {
                    ElectionRepresentationActivated::dispatch($assignment);
                }
            }

            return $direct->refresh();
        }, 3);
    }

    public function revoke(ElectionAppointment $appointment, string $reason, string $actor = 'election_appointment_service'): ElectionAppointment
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Appointment revocation reason is required.');
        }
        if (trim($actor) === '') {
            throw new InvalidArgumentException('Appointment revocation actor is required.');
        }

        return DB::transaction(function () use ($appointment, $reason, $actor): ElectionAppointment {
            $locked = ElectionAppointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($locked->status !== 'active') {
                return $locked;
            }

            $this->revokeChain($locked, trim($reason), trim($actor));

            return $locked->refresh();
        }, 3);
    }

    public function summary(Election $election): array
    {
        return [
            'election_id' => (int) $election->id,
            'lifecycle_status' => $this->lifecycle->currentStatus($election)->value,
            'direct_appointments' => ElectionAppointment::query()
                ->where('election_id', $election->id)
                ->where('appointment_kind', 'direct')
                ->where('status', 'active')
                ->count(),
            'inherited_appointments' => ElectionAppointment::query()
                ->where('election_id', $election->id)
                ->where('appointment_kind', 'inherited')
                ->where('status', 'active')
                ->count(),
        ];
    }

    private function isFilled(Election $election): bool
    {
        // Seat completion is retrospective to the challenged cycle. A later
        // admin policy change must never change whether this election is full.
        try {
            $policy = $this->policyResolver->resolveForElection($election);
        } catch (RuntimeException) {
            // Legacy cycles that predate policy-version linkage retain the
            // compatibility lookup until their historical data is reconciled.
            $policy = $this->policyResolver->resolveForGroup($election->group);
        }

        $required = [
            ElectionPosition::Manager->value => $this->policyResolver->managerSeatCount($policy),
            ElectionPosition::Inspector->value => $this->policyResolver->inspectorSeatCount($policy),
        ];

        foreach ($required as $position => $seatCount) {
            $appointed = ElectionAppointment::query()
                ->where('election_id', $election->id)
                ->where('group_id', $election->group_id)
                ->where('position', $position)
                ->where('appointment_kind', 'direct')
                ->where('status', 'active')
                ->count();

            if ($appointed < $seatCount) {
                return false;
            }
        }

        return true;
    }

    private function assertSourceMembershipStillValid(Group $group, User $user): void
    {
        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null || (int) $membership->status !== 1 || (int) $membership->role === 4 || (bool) $user->is_system) {
            throw new RuntimeException('Accepted candidate is no longer eligible for appointment in the source group.');
        }
    }

    private function assertNoSameGroupPositionConflict(Group $group, User $user, ElectionPosition $position): void
    {
        $conflict = ElectionAppointment::query()
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->where('position', '!=', $position->value)
            ->exists();

        if ($conflict) {
            throw new RuntimeException('User already holds a conflicting active election responsibility in this group.');
        }
    }

    private function applyResponsibilityMembership(Group $group, User $user, int $role): void
    {
        $membership = GroupUser::withTrashed()->firstOrNew([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        if ($membership->trashed()) {
            $membership->restore();
        }
        $membership->role = $role;
        $membership->status = 1;
        $membership->expired = null;
        $membership->save();
    }

    private function applyRepresentativeMembership(Group $group, User $user): void
    {
        $membership = GroupUser::withTrashed()->firstOrNew([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        if ($membership->trashed()) {
            $membership->restore();
        }

        if (! in_array((int) $membership->role, [self::MANAGER_ROLE, self::INSPECTOR_ROLE], true)) {
            $membership->role = self::ACTIVE_MEMBER_ROLE;
        }
        $membership->status = 1;
        $membership->expired = null;
        $membership->save();
    }

    private function supersedeLowerIndependentAppointments(
        ElectionAppointment $newAppointment,
        Group $newGroup,
        User $user,
        ElectionPosition $position,
    ): void {
        $newIndex = $this->hierarchy->hierarchyIndex($newGroup, $user);
        $active = ElectionAppointment::query()
            ->where('user_id', $user->id)
            ->where('position', $position->value)
            ->where('appointment_kind', 'direct')
            ->where('status', 'active')
            ->where('id', '!=', $newAppointment->id)
            ->with('group')
            ->get();

        foreach ($active as $older) {
            if (! $this->hierarchy->sameTrack($newGroup, $older->group)) {
                continue;
            }

            $olderIndex = $this->hierarchy->hierarchyIndex($older->group, $user);
            if ($olderIndex <= $newIndex) {
                continue;
            }

            $older->forceFill([
                'status' => 'superseded',
                'ended_at' => now(),
                'superseded_by_appointment_id' => $newAppointment->id,
                'reason' => 'higher_valid_election_seat_appointed',
            ])->save();

            $this->endRepresentation($older, 'source_appointment_superseded_by_higher_valid_seat');
            $this->supersedeInheritedDescendants($older, $newAppointment, $user);
            $this->demoteMembershipIfNoActiveResponsibility($user->id, $older->group_id);
        }
    }

    private function supersedeInheritedDescendants(
        ElectionAppointment $source,
        ElectionAppointment $superseding,
        User $user,
    ): void {
        $children = ElectionAppointment::query()
            ->where('source_appointment_id', $source->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $this->supersedeInheritedDescendants($child, $superseding, $user);

            $child->forceFill([
                'status' => 'superseded',
                'ended_at' => now(),
                'superseded_by_appointment_id' => $superseding->id,
                'reason' => 'inherited_responsibility_replaced_by_higher_valid_election_seat',
            ])->save();

            $this->endRepresentation($child, 'inherited_source_chain_superseded_by_higher_valid_seat');
            $this->demoteMembershipIfNoActiveResponsibility($user->id, $child->group_id);
        }
    }

    private function revokeChain(ElectionAppointment $appointment, string $reason, string $actor): void
    {
        $children = ElectionAppointment::query()
            ->where('source_appointment_id', $appointment->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $this->revokeChain($child, 'source_appointment_revoked: '.$reason, $actor);
        }

        $appointment->forceFill([
            'status' => 'revoked',
            'ended_at' => now(),
            'actor' => $actor,
            'reason' => $reason,
        ])->save();

        $this->endRepresentation($appointment, 'appointment_revoked: '.$reason);
        $this->demoteMembershipIfNoActiveResponsibility((int) $appointment->user_id, (int) $appointment->group_id);
        ElectionAppointmentRevoked::dispatch($appointment);
    }

    private function endRepresentation(ElectionAppointment $appointment, string $reason): void
    {
        ElectionRepresentationAssignment::query()
            ->where('appointment_id', $appointment->id)
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
                'reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    private function demoteMembershipIfNoActiveResponsibility(int $userId, int $groupId): void
    {
        $stillResponsible = ElectionAppointment::query()
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->exists();

        if ($stillResponsible) {
            return;
        }

        GroupUser::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->whereIn('role', [self::MANAGER_ROLE, self::INSPECTOR_ROLE])
            ->update(['role' => self::ACTIVE_MEMBER_ROLE, 'updated_at' => now()]);
    }

    private function groupRole(ElectionPosition $position): int
    {
        return match ($position) {
            ElectionPosition::Manager => self::MANAGER_ROLE,
            ElectionPosition::Inspector => self::INSPECTOR_ROLE,
        };
    }
}
