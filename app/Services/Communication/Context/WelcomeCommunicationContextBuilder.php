<?php

namespace App\Services\Communication\Context;

use App\Models\User;

final class WelcomeCommunicationContextBuilder
{
    /** @return array{display_name:string,email:string,profile_url:string} */
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
            'profile_url' => route('profile.show'),
        ];
    }
}
