<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use App\Models\UserExperience;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Support\Facades\Log;

class ProfileCompletionService
{
    public function isComplete(User $user): bool
    {
        $step1Complete = $user->first_name
            && $user->last_name
            && $user->gender
            && $user->national_id
            && $user->phone;
        $hasExperience = UserExperience::where('user_id', $user->id)->exists();
        $hasAddress = Address::where('user_id', $user->id)->exists();

        return (bool) ($step1Complete && $hasExperience && $hasAddress);
    }

    public function maybeAward(User $user): bool
    {
        if (! $this->isComplete($user)) {
            return false;
        }

        $alreadyAwarded = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'profile_completed')
            ->exists();

        if ($alreadyAwarded) {
            return false;
        }

        try {
            app(ReputationService::class)->applyAction(
                $user,
                'profile_completed',
                [],
                null,
                'profile',
                'profile_completed:user:' . $user->id
            );
            return true;
        } catch (\Throwable $e) {
            Log::error('Reputation applyAction failed (profile_completed): ' . $e->getMessage());
            return false;
        }
    }
}
