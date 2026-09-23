<?php

namespace App\Services\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\LocationProposal;
use App\Models\Setting;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class LocationProposalSupportService
{
    private const OPEN_STATUSES = [
        LocationProposalStatus::Pending->value,
        LocationProposalStatus::ReadyForReview->value,
        LocationProposalStatus::NeedsEvidence->value,
    ];

    public function record(LocationProposal $proposal, User $user, array $evidence): void
    {
        $proposal->refresh();
        if (! in_array($proposal->status->value, self::OPEN_STATUSES, true)) {
            throw new DomainException('This location proposal is already resolved.');
        }

        DB::transaction(function () use ($proposal, $user, $evidence): void {
            $proposal->evidence()->updateOrCreate(
                ['user_id' => $user->id],
                ['evidence' => $evidence],
            );

            $proposal->refresh();
            $threshold = max(
                1,
                (int) (
                    Setting::singleton()->location_proposal_verification_threshold
                    ?? config('location-governance.location_proposal_verification_threshold', 10)
                ),
            );
            $distinctVerifiers = $proposal->evidence()->distinct()->count('user_id');

            if ($proposal->status === LocationProposalStatus::Pending && $distinctVerifiers >= $threshold) {
                $audit = $proposal->audit_log ?? [];
                $audit[] = [
                    'from' => LocationProposalStatus::Pending->value,
                    'to' => LocationProposalStatus::ReadyForReview->value,
                    'actor_user_id' => $user->id,
                    'reason' => 'verification_threshold_reached',
                    'at' => now()->toIso8601String(),
                ];

                $proposal->forceFill([
                    'status' => LocationProposalStatus::ReadyForReview,
                    'audit_log' => $audit,
                ])->save();
            }
        });
    }
}
