<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use App\Models\UserExperience;
use App\Models\UserPointTransaction;
use App\Services\LocationGovernance\LocationTreeResolver;
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
        $hasResidence = $this->hasRequiredResidence($user);

        return (bool) ($step1Complete && $hasExperience && $hasResidence);
    }

    public function hasRequiredResidence(User $user): bool
    {
        $canonicalResidenceEnabled = (bool) config('location-governance.runtime_enabled')
            && (bool) config('location-governance.registration_enabled');

        if (! $canonicalResidenceEnabled) {
            return Address::where('user_id', $user->id)->exists();
        }

        $primaryResidence = $user->locationRelationships()
            ->with('location')
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($primaryResidence?->location === null) {
            return false;
        }

        // Canonical runtime validates the residence through the Location tree;
        // it never needs a shadow legacy Address row merely to enter the app.
        app(LocationTreeResolver::class)->ancestors($primaryResidence->location);

        return true;
    }

    public function maybeAward(User $user): bool
    {
        if (! $this->isComplete($user)) {
            return false;
        }

        $changed = false;

        $alreadyAwarded = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'profile_completed')
            ->exists();

        if (! $alreadyAwarded) {
            try {
                app(ReputationService::class)->applyAction(
                    $user,
                    'profile_completed',
                    [],
                    null,
                    'profile',
                    'profile_completed:user:' . $user->id
                );
                $changed = true;
            } catch (\Throwable $e) {
                Log::error('Reputation applyAction failed (profile_completed): ' . $e->getMessage());
            }
        }

        // Registration completion is also the canonical success boundary for a
        // member referral. This is intentionally independent of Najm Bahar
        // account creation or agreement acceptance.
        try {
            if (app(InvitationLifecycleService::class)->completeSuccessfulInvitation($user)) {
                $changed = true;
            }
        } catch (\Throwable $e) {
            // Do not block registration completion if the referral reward path
            // is temporarily unavailable. Because completed_at is transactional
            // with the reward, a later maybeAward() call can safely retry it.
            Log::error('Invitation completion failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }

        return $changed;
    }
}
