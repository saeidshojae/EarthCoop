<?php

namespace App\Services\Elections;

use App\Models\User;

class CurrentElectionCenterService
{
    public function forUser(User $user): array
    {
        return [
            'systemic' => collect(),
            'internal' => collect(),
            'summary' => [
                'action_required' => 0,
                'related' => 0,
                'total' => 0,
            ],
        ];
    }
}
