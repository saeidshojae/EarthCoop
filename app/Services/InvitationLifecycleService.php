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
        protected MembershipParticipationEligibilityService $participationEligibility,
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
     * Successful referrals permanently consume a slot. A not-yet-completed
     * invitation reserves a slot only while its configured validity window is
     * still open. Therefore unused or abandoned/claimed registrations release
     * their slot after expiry and can be replaced by a new invitation.
     */
    public function occupiedSlots(User $referrer): int
    {
        return InvitationCode::where('user_id', $referrer->id)
            ->where(function ($query) {
                $query->whereNotNull('completed_at')
                    ->orWhere(function ($live) {
                        $live->whereNull('completed_at')
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
            && $this->participationEligibility->isEligible($referrer)
            && $this->quota() > 0
            && $this->occupiedSlots($referrer) < $this->quota();
    }

    public function issueMemberInvitation(User $referrer): InvitationCode
    {
        return DB::transaction(function () use ($referrer) {
            // Serialize issuance per referrer. The public canIssue... method is
            // only a preview for UI; this locked check is the authority.
            $lockedReferrer = User::whereKey($referrer->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedReferrer
                || ! $this->isEligibleMember($lockedReferrer)
                || ! $this->participationEligibility->isEligible($lockedReferrer)
                || $this->quota() <= 0
                || $this->occupiedSlots($lockedReferrer) >= $this->quota()) {
                throw new RuntimeException('Invitation quota is exhausted or member is not eligible.');
            }

            do {
                $code = Str::upper(Str::random(8));
            } while (InvitationCode::where('code', $code)->exists());

            return InvitationCode::create([
                'code' => $code,
                'user_id' => $lockedReferrer->id,
                'used' => false,
                'expire_at' => now()->addHours($this->expiryHours()),
            ]);
        });
    }

    /**
     * Finalize one member referral exactly once when the invitee completes the
     * canonical registration/profile lifecycle. The reputation write and the
     * completion marker share a transaction so a failed reward can be retried.
     */
    public function completeSuccessfulInvitation(User $invitee): bool
    {
        if (! $this->isEligibleMember($invitee)) {
            return false;
        }

        return DB::transaction(function () use ($invitee) {
            $invitation = InvitationCode::where('used_by', $invitee->id)
                ->lockForUpdate()
                ->first();

            if (! $invitation
                || $invitation->completed_at !== null
                || ! $invitation->user_id
                || ! $invitation->expire_at
                || $invitation->expire_at->lt(now())) {
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

            // The quota represents successful referrals, not generated codes.
            // Re-check it under the same transaction so late/concurrent profile
            // completions cannot create more rewards than the configured limit.
            $successfulBefore = InvitationCode::where('user_id', $referrer->id)
                ->whereNotNull('completed_at')
                ->lockForUpdate()
                ->count();

            if ($successfulBefore >= $this->quota()) {
                return false;
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
