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

    /**
     * Proposal support is evidence of an actually committed residence choice.
     * It is intentionally not a generic "like/support" action.
     */
    public function recordCommitted(LocationProposal $proposal, User $user, array $evidence): void
    {
        if (! in_array(($evidence['source'] ?? null), ['residence_commit', 'residence_commit_reconcile'], true)) {
            throw new DomainException('Location proposal support must come from a committed residence selection.');
        }

        DB::transaction(function () use ($proposal, $user, $evidence): void {
            $locked = LocationProposal::query()
                ->with(['type', 'parentProposal.type'])
                ->whereKey($proposal->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $locked->status instanceof LocationProposalStatus
                ? $locked->status->value
                : (string) $locked->status;

            if (! in_array($status, self::OPEN_STATUSES, true)) {
                throw new DomainException('This location proposal is already resolved.');
            }

            if (! app(LocationProposalPolicy::class)->storedStructuralProvenanceIsValid($locked)) {
                throw new DomainException('The proposal structural dependencies are no longer valid.');
            }

            $locked->evidence()->updateOrCreate(
                ['user_id' => $user->id],
                ['evidence' => $evidence],
            );

            $threshold = max(
                1,
                (int) (
                    Setting::singleton()->location_proposal_verification_threshold
                    ?? config('location-governance.location_proposal_verification_threshold', 10)
                ),
            );
            $distinctVerifiers = $locked->evidence()->distinct()->count('user_id');

            if ($locked->status === LocationProposalStatus::Pending && $distinctVerifiers >= $threshold) {
                $audit = $locked->audit_log ?? [];
                $audit[] = [
                    'from' => LocationProposalStatus::Pending->value,
                    'to' => LocationProposalStatus::ReadyForReview->value,
                    'actor_user_id' => $user->id,
                    'reason' => 'verification_threshold_reached',
                    'at' => now()->toIso8601String(),
                ];

                $locked->forceFill([
                    'status' => LocationProposalStatus::ReadyForReview,
                    'audit_log' => $audit,
                ])->save();
            }
        });
    }
}
