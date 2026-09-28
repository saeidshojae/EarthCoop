<?php

declare(strict_types=1);

namespace App\Services\Actors;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ActorResolver
{
    public function referenceFor(User|Group $model): ActorReference
    {
        if ($model instanceof User) {
            if ((bool) ($model->is_system ?? false)) {
                throw ActorBoundaryException::notSupported();
            }

            return new ActorReference(ActorType::User, (string) $model->getKey());
        }

        return new ActorReference(ActorType::Group, (string) $model->getKey());
    }

    public function fromLegacyOwner(string $ownerType, int|string $ownerId): ActorReference
    {
        return match ($ownerType) {
            User::class => new ActorReference(ActorType::User, (string) $ownerId),
            Group::class => new ActorReference(ActorType::Group, (string) $ownerId),
            default => throw ActorBoundaryException::notSupported(),
        };
    }

    public function resolveModel(ActorReference $actor): User|Group
    {
        $model = match ($actor->type()) {
            ActorType::User => User::query()
                ->whereKey($actor->id())
                ->where('is_system', false)
                ->first(),
            ActorType::Group => Group::query()->whereKey($actor->id())->first(),
            ActorType::Organization, ActorType::System => throw ActorBoundaryException::notSupported(),
        };

        if (! $model instanceof Model) {
            throw ActorBoundaryException::notFound();
        }

        return $model;
    }

    public function systemReference(): ActorReference
    {
        return new ActorReference(ActorType::System, 'earthcoop');
    }
}
