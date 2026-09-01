<?php

namespace App\Services;

use App\Models\Address;
use App\Models\InvitationCode;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvitationLifecycleService
{
    public function __construct(
        protected ReputationService $reputationService,
    ) {
    }

    public function quota(): int
    {
        return max(0, (int) (Setting::find(1)?->count_invation ?? 10));
    }

    public function expiryHours(): int
    {
        return max(1, (int) (Setting::find(1)?->expire_invation_time ?? 72));
    }

    public function isEligibleMember(User $user): bool
    {
        if (method_exists($user, 'isSystemIdentity') && $user->isSystemIdentity()) {
            return false;
        }

        $identityComplete = $user->first_name
            && $user->last_name
            && $user->gender
            && $user->national_id
            && $user->phone;

        return (bool) $identityComplete
            && UserExperience::where('user_id', $user->id)->exists()
            && Address::where('user_id', $user->id)->exists();
    }

    /**
     * A member has a configurable number of invitation slots. A completed
     * invitation permanently consumes one slot. A currently usable code or a
     * code already claimed by an incomplete registrant temporarily reserves a
     * slot. An expired, unclaimed code releases its slot automatically.
     */
    public function occupiedSlots(User $referrer): int
    {
        return InvitationCode::where('user_id', $referrer->id)
            ->where(function ($query) {
                $query->whereNotNull('completed_at')
                    ->orWhereNotNull('used_by')
                    ->orWhere(function ($active) {
                        $active->where('used', false)
                            ->whereNotNull('expire_at')
                            ->where('expire_at', '>=', now());
                    });
            })
            ->count();
    }

    public function successfulInvitations(User $referrer): int
    {
        return InvitationCode::where('user_id', $referrer->id)
            ->whereNotNull('completed_at')
            ->count();
    }

    public function remainingSlots(User $referrer): int
    {
        return max(0, $this->quota() - $this->occupiedSlots($referrer));
    }

    public function canIssueMemberInvitation(User $referrer): bool
    {
        return $this->isEligibleMember($referrer)
            && $this->quota() > 0
            && $this->occupiedSlots($referrer) < $this->quota();
    }

    public function issueMemberInvitation(User $referrer): InvitationCode
    {
        if (! $this->canIssueMemberInvitation($referrer)) {
            throw new RuntimeException('Invitation quota is exhausted or member is not eligible.');
        }

        do {
            $code = Str::upper(Str::random(8));
        } while (InvitationCode::where('code', $code)->exists());

        return InvitationCode::create([
            'code' => $code,
            'user_id' => $referrer->id,
            'used' => false,
            'expire_at' => now()->addHours($this->expiryHours()),
        ]);
    }

    /**
     * Finalize one member referral exactly once when the invitee completes the
     * canonical registration/profile lifecycle. The reputation write and the
     * completion marker share a transaction so a failed reward can be retried.
     */
    public function completeSuccessfulInvitation(User $invitee): bool
    {
        return DB::transaction(function () use ($invitee) {
            $invitation = InvitationCode::where('used_by', $invitee->id)
                ->lockForUpdate()
                ->first();

            if (! $invitation || $invitation->completed_at !== null || ! $invitation->user_id) {
                return false;
            }

            $referrer = User::whereKey($invitation->user_id)->lockForUpdate()->first();
            if (! $referrer) {
                return false;
            }

            if (method_exists($referrer, 'isSystemIdentity') && $referrer->isSystemIdentity()) {
                $invitation->forceFill(['completed_at' => now()])->save();
                return true;
            }

            // Compatibility with the historical system/admin issuer until all
            // old invitation rows have a canonical system-identity issuer.
            if ((int) $referrer->id === 171) {
                $invitation->forceFill(['completed_at' => now()])->save();
                return true;
            }

            $completedBefore = InvitationCode::where('user_id', $referrer->id)
                ->whereNotNull('completed_at')
                ->where('id', '!=', $invitation->id)
                ->lockForUpdate()
                ->count();

            if ($completedBefore >= $this->quota()) {
                throw new RuntimeException('Successful invitation quota has already been exhausted.');
            }

            $this->reputationService->applyAction(
                $referrer,
                'invite_member',
                [
                    'new_user_id' => $invitee->id,
                    'invitation_code_id' => $invitation->id,
                    'economic_rule' => 'participation_points_only_no_dim_transfer',
                    'completion_event' => 'registration_completed',
                ],
                $invitation->id,
                'registration_completion',
                'invite_member:referrer:' . $referrer->id . ':member:' . $invitee->id
            );

            $invitation->forceFill(['completed_at' => now()])->save();

            return true;
        });
    }
}
