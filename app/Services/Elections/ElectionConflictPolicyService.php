<?php

namespace App\Services\Elections;

use App\Models\ElectionAppointment;
use App\Models\ElectionConflictPolicyRule;
use App\Models\ElectionConflictPolicyVersion;
use App\Models\ElectionRepresentationAssignment;
use App\Models\ElectionVacancy;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionConflictPolicyService
{
    public function __construct(private readonly ElectionGroupDomainClassifier $classifier) {}

    public function effectiveVersion(): ElectionConflictPolicyVersion
    {
        $policy = ElectionConflictPolicyVersion::query()
            ->where('effective_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('retired_at')->orWhere('retired_at', '>', now()))
            ->orderByDesc('effective_at')->orderByDesc('version')->first();
        if ($policy === null) {
            throw new RuntimeException('No effective election conflict policy version exists; appointment is fail-closed.');
        }
        return $policy;
    }

    public function decision(ElectionAppointment $current, Group $newGroup, string $newPosition): string
    {
        $currentGroup = $current->group()->firstOrFail();
        $rule = ElectionConflictPolicyRule::query()
            ->where('policy_version_id', $this->effectiveVersion()->id)
            ->where('current_position', $current->position)
            ->where('current_domain_type', $this->classifier->domain($currentGroup))
            ->where('current_level', $this->classifier->level($currentGroup))
            ->where('new_position', $newPosition)
            ->where('new_domain_type', $this->classifier->domain($newGroup))
            ->where('new_level', $this->classifier->level($newGroup))
            ->first();
        return $rule?->decision ?? 'allowed';
    }

    public function enforceBeforeDirectAppointment(User $user, Group $newGroup, string $newPosition): void
    {
        DB::transaction(function () use ($user, $newGroup, $newPosition): void {
            $active = ElectionAppointment::query()
                ->where('user_id', $user->id)
                ->where('appointment_kind', 'direct')
                ->where('status', 'active')
                ->with('group')
                ->lockForUpdate()
                ->get();

            $suspend = collect();
            foreach ($active as $current) {
                $decision = $this->decision($current, $newGroup, $newPosition);
                if ($decision === 'forbidden') {
                    throw new RuntimeException('Election conflict policy forbids accepting the new responsibility while the current responsibility is active.');
                }
                if ($decision === 'allowed_with_suspension') {
                    $suspend->push($current);
                }
            }

            foreach ($suspend as $current) {
                $this->suspendChain($current, $newGroup, $newPosition);
            }
        }, 3);
    }

    private function suspendChain(ElectionAppointment $appointment, Group $newGroup, string $newPosition): void
    {
        $children = ElectionAppointment::query()
            ->where('source_appointment_id', $appointment->id)
            ->where('status', 'active')->lockForUpdate()->get();
        foreach ($children as $child) {
            $this->suspendChain($child, $newGroup, $newPosition);
        }

        $appointment->forceFill([
            'status' => 'suspended',
            'ended_at' => now(),
            'actor' => 'election_conflict_policy_service',
            'reason' => 'incompatible_new_responsibility_accepted',
            'metadata' => array_merge($appointment->metadata ?? [], [
                'conflict_policy_version_id' => $this->effectiveVersion()->id,
                'new_group_id' => (int) $newGroup->id,
                'new_position' => $newPosition,
                'automatic_return' => false,
            ]),
        ])->save();

        ElectionRepresentationAssignment::query()
            ->where('appointment_id', $appointment->id)->where('status', 'active')
            ->update(['status' => 'ended', 'ended_at' => now(), 'reason' => 'source_appointment_suspended_by_conflict_policy']);

        if ($appointment->appointment_kind === 'direct') {
            ElectionVacancy::query()->firstOrCreate(
                ['source_appointment_id' => $appointment->id],
                [
                    'election_id' => $appointment->election_id,
                    'user_id' => $appointment->user_id,
                    'group_id' => $appointment->group_id,
                    'position' => $appointment->position,
                    'continuity_mode' => 'immediate',
                    'status' => 'open',
                    'opened_at' => now(),
                    'actor' => 'election_conflict_policy_service',
                    'reason' => 'appointment_suspended_due_to_incompatible_accepted_responsibility',
                    'metadata' => ['new_group_id' => (int) $newGroup->id, 'new_position' => $newPosition],
                ],
            );
        }

        $remaining = ElectionAppointment::query()
            ->where('user_id', $appointment->user_id)
            ->where('group_id', $appointment->group_id)
            ->where('status', 'active')->exists();
        if (! $remaining) {
            GroupUser::query()->where('group_id', $appointment->group_id)->where('user_id', $appointment->user_id)
                ->whereIn('role', [2, 3])->update(['role' => 1]);
        }
    }
}
