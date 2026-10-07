<?php

namespace App\Services\Communication\Context;

use App\Models\User;

final class WelcomeCommunicationContextBuilder
{
    /** @return array<string,string> */
    public function build(User $user): array
    {
        $displayName = trim(implode(' ', array_filter([
            trim((string) $user->first_name),
            trim((string) $user->last_name),
        ], fn (string $part): bool => $part !== '')));

        if ($displayName === '') {
            $displayName = (string) $user->email;
        }

        return [
            'display_name' => $displayName,
            'email' => (string) $user->email,
            'home_url' => route('home'),
            'profile_url' => route('profile.show'),
            'groups_url' => route('groups.index'),
            'participation_url' => route('history.index'),
            'governance_url' => route('location-governance.me'),
            'communication_preferences_url' => route('profile.communication-preferences'),
        ];
    }
}
