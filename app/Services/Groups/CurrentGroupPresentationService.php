<?php

namespace App\Services\Groups;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read-only presentation snapshot matching the canonical My Groups rules.
 * Pending shells are not persisted group memberships.
 */
final class CurrentGroupPresentationService
{
    public function __construct(
        private readonly CurrentGroupAccessService $access,
        private readonly PendingLocationGroupRequestService $pending,
    ) {
    }

    /** @return array{materialized:Collection,pending:Collection,all:Collection} */
    public function forUser(User $user): array
    {
        $materialized = $this->access->groupsFor($user);
        if (! (bool) config('location-governance.groups_enabled', false)) {
            return ['materialized' => $materialized, 'pending' => collect(), 'all' => $materialized];
        }

        $requests = $this->pending->readOpenForUser($user);
        $materialized = $this->pending->presentableCanonicalGroups($materialized, $requests);
        $pending = $this->pending->presentationGroups($requests);

        return [
            'materialized' => $materialized,
            'pending' => $pending,
            'all' => $materialized->concat($pending)->values(),
        ];
    }
}
