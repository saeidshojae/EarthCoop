<?php

namespace App\Observers\NajmHoda;

use App\Models\User;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class FounderUserObserver
{
    public function created(User $user): void
    {
        if ($user->isSystemIdentity()) {
            return;
        }

        $this->bus()->emit('najm_hoda.input.founder.user.created', [
            'user_id' => (int) $user->id,
            'status' => $user->status,
            'email_verified' => $user->email_verified_at !== null,
            'profile_completion' => [
                'occupational_status' => $user->occupational_status,
                'experience_status' => $user->experience_status,
            ],
            'scope' => 'founder_operations',
            'category' => 'users',
            'risk' => 'low',
            'action_required' => false,
        ]);
    }

    public function updated(User $user): void
    {
        if ($user->isSystemIdentity()) {
            return;
        }

        $watched = [
            'status',
            'email_verified_at',
            'occupational_status',
            'experience_status',
        ];

        $changes = array_values(array_intersect($watched, array_keys($user->getChanges())));
        if ($changes === []) {
            return;
        }

        $this->bus()->emit('najm_hoda.input.founder.user.updated', [
            'user_id' => (int) $user->id,
            'changed_fields' => $changes,
            'status' => $user->status,
            'email_verified' => $user->email_verified_at !== null,
            'profile_completion' => [
                'occupational_status' => $user->occupational_status,
                'experience_status' => $user->experience_status,
            ],
            'scope' => 'founder_operations',
            'category' => 'users',
            'risk' => 'low',
            'action_required' => false,
        ]);
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);

        return $bus;
    }
}
