<?php

declare(strict_types=1);

namespace App\Services\Actors;

use App\Models\Group;
use App\Models\User;
use App\Services\Groups\EffectiveGroupMembershipService;

final class ActorDiscoveryService
{
    public function __construct(
        private readonly ActorResolver $resolver,
        private readonly EffectiveGroupMembershipService $memberships,
    ) {
    }

    public function for(User $principal): array
    {
        $self = $this->resolver->referenceFor($principal);
        $items = [[
            ...$self->toArray(),
            'display_name' => $this->userDisplayName($principal),
            'context' => [],
            'permissions' => ['can_represent' => true],
        ]];

        $managedGroups = $this->memberships->currentForUser($principal)
            ->filter(fn ($membership): bool => (int) $membership->role === 3)
            ->sortBy('group_id');

        foreach ($managedGroups as $membership) {
            $group = $membership->group()->first();
            if (! $group instanceof Group) {
                continue;
            }

            $reference = $this->resolver->referenceFor($group);
            $items[] = [
                ...$reference->toArray(),
                'display_name' => (string) $group->name,
                'context' => [
                    'governance_area_id' => $group->governance_area_id ? (int) $group->governance_area_id : null,
                    'dimension_key' => $group->dimension_key !== null ? (string) $group->dimension_key : null,
                    'dimension_value_key' => $group->dimension_value_key !== null ? (string) $group->dimension_value_key : null,
                ],
                'permissions' => ['can_represent' => true],
            ];
        }

        return ['items' => $items];
    }

    private function userDisplayName(User $user): string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->last_name ?? '')),
        ])));

        return $name !== '' ? $name : (string) $user->email;
    }
}
