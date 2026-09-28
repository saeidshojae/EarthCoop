<?php

declare(strict_types=1);

namespace App\Services\Actors;

use App\Models\Group;
use App\Models\User;
use App\Services\Groups\EffectiveGroupMembershipService;

final class ActorRepresentationAuthorizationService
{
    public function __construct(
        private readonly ActorResolver $resolver,
        private readonly EffectiveGroupMembershipService $memberships,
    ) {
    }

    public function allows(User $principal, ActorReference $actor, ActorOperation $operation): bool
    {
        try {
            $this->authorize($principal, $actor, $operation);

            return true;
        } catch (ActorBoundaryException) {
            return false;
        }
    }

    public function authorize(User $principal, ActorReference $actor, ActorOperation $operation): void
    {
        if ($operation !== ActorOperation::ProjectOwner) {
            throw ActorBoundaryException::operationNotSupported();
        }

        if ($actor->type() === ActorType::Organization) {
            throw ActorBoundaryException::notSupported();
        }

        if ($actor->type() === ActorType::System) {
            throw ActorBoundaryException::representationForbidden();
        }

        if ((bool) ($principal->is_system ?? false)) {
            throw ActorBoundaryException::representationForbidden();
        }

        if ($actor->type() === ActorType::User) {
            if ($actor->id() !== (string) $principal->getKey()) {
                throw ActorBoundaryException::representationForbidden();
            }

            return;
        }

        $group = $this->resolver->resolveModel($actor);
        if (! $group instanceof Group) {
            throw ActorBoundaryException::operationNotSupported();
        }

        $membership = $this->memberships->current($principal, $group);
        if (! $membership || (int) $membership->role !== 3) {
            throw ActorBoundaryException::representationForbidden();
        }
    }
}
