<?php

declare(strict_types=1);

namespace App\Services\Actors;

use App\Models\User;

final class OwnerRepresentationService
{
    public function __construct(
        private readonly ActorResolver $resolver,
        private readonly ActorRepresentationAuthorizationService $representation,
    ) {
    }

    public function referenceFor(string $ownerType, int|string $ownerId): ActorReference
    {
        return $this->resolver->fromLegacyOwner($ownerType, $ownerId);
    }

    public function allows(
        User $principal,
        string $ownerType,
        int|string $ownerId,
        ActorOperation $operation,
    ): bool {
        try {
            $actor = $this->referenceFor($ownerType, $ownerId);

            return $this->representation->allows($principal, $actor, $operation);
        } catch (ActorBoundaryException) {
            return false;
        }
    }

    public function authorize(
        User $principal,
        string $ownerType,
        int|string $ownerId,
        ActorOperation $operation,
    ): void {
        $actor = $this->referenceFor($ownerType, $ownerId);
        $this->representation->authorize($principal, $actor, $operation);
    }
}
