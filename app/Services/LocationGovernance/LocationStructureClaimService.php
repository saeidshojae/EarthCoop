<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\ReferenceSettlement;
use App\Models\Setting;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use Illuminate\Support\Facades\DB;

class LocationStructureClaimService
{
    public const OPEN_STATUSES = ['pending', 'ready_for_review', 'needs_evidence'];

    public function findOrCreateOpenClaim(Location $location, string $type, User $proposer): LocationStructureClaim
    {
        $contextClaims = LocationStructureClaim::query()
            ->where('location_id', $location->id)
            ->whereIn('status', array_merge(self::OPEN_STATUSES, ['approved']))
            ->get();

        if (! app(LocationStructureClaimPolicy::class)->allowsClaimType($location, $type, $contextClaims)) {
            throw new \DomainException('Structural claim type is not allowed for this location type.');
        }

        $conflicts = [
            'single_urban_region' => ['no_urban_region'],
            'no_urban_region' => ['single_urban_region'],
            'single_neighborhood' => ['no_neighborhood'],
            'no_neighborhood' => ['single_neighborhood'],
        ];

        if ($contextClaims->pluck('claim_type')->intersect($conflicts[$type] ?? [])->isNotEmpty()) {
            throw new \DomainException('Conflicting structural claim already exists for this location tier.');
        }

        return DB::transaction(function () use ($location, $type, $proposer): LocationStructureClaim {
            $claim = LocationStructureClaim::query()
                ->where('location_id', $location->id)
                ->where('claim_type', $type)
                ->whereIn('status', self::OPEN_STATUSES)
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            return $claim ?? LocationStructureClaim::query()->create([
                'location_id' => $location->id,
                'claim_type' => $type,
                'status' => 'pending',
                'proposer_user_id' => $proposer->id,
                'audit_log' => [],
            ]);
        });
    }

    public function findOrCreateOpenReferenceSettlementClaim(
        ReferenceSettlement $settlement,
        string $type,
        User $proposer,
    ): LocationStructureClaim {
        if ($type !== 'no_neighborhood') {
            throw new \DomainException('Only no-neighborhood is exposed for a reference settlement.');
        }

        $claimable = (
            in_array($settlement->classification, ['unverified_settlement', 'needs_review'], true)
            && $settlement->residential_eligibility === 'unverified'
        ) || (
            $settlement->classification === 'verified_residential_village'
            && $settlement->residential_eligibility === 'verified'
        );

        if (! $claimable || $settlement->governance_authorized || $settlement->operational_promotion_allowed) {
            throw new \DomainException('This reference settlement cannot accept an open structural claim.');
        }

        return DB::transaction(function () use ($settlement, $type, $proposer): LocationStructureClaim {
            return LocationStructureClaim::query()
                ->where('reference_settlement_id', $settlement->id)
                ->where('claim_type', $type)
                ->whereIn('status', self::OPEN_STATUSES)
                ->lockForUpdate()
                ->orderBy('id')
                ->first()
                ?? LocationStructureClaim::query()->create([
                    'location_id' => null,
                    'location_proposal_id' => null,
                    'reference_settlement_id' => $settlement->id,
                    'claim_type' => $type,
                    'status' => 'pending',
                    'proposer_user_id' => $proposer->id,
                    'audit_log' => [],
                ]);
        });
    }

    public function recordCommittedSupport(LocationStructureClaim $claim, User $user, array $evidence): void
    {
        DB::transaction(function () use ($claim, $user, $evidence): void {
            $claim = LocationStructureClaim::query()->lockForUpdate()->findOrFail($claim->id);

            if (! in_array($claim->status, self::OPEN_STATUSES, true)) {
                return;
            }

            $claim->evidence()->updateOrCreate(['user_id' => $user->id], ['evidence' => $evidence]);
            $threshold = max(1, (int) (Setting::singleton()->location_structure_claim_verification_threshold ?? config('location-governance.location_structure_claim_verification_threshold', 10)));

            if ($claim->status === 'pending' && $claim->evidence()->distinct()->count('user_id') >= $threshold) {
                $audit = $claim->audit_log ?? [];
                $audit[] = [
                    'from' => 'pending',
                    'to' => 'ready_for_review',
                    'actor_user_id' => $user->id,
                    'reason' => 'verification_threshold_reached',
                    'at' => now()->toIso8601String(),
                ];
                $claim->forceFill(['status' => 'ready_for_review', 'audit_log' => $audit])->save();
            }
        });
    }
    public function markNeedsEvidence(LocationStructureClaim $claim, User $reviewer, string $reason): LocationStructureClaim
    {
        return $this->reviewTransition($claim, $reviewer, 'needs_evidence', $reason);
    }

    public function approve(LocationStructureClaim $claim, User $reviewer, string $reason): LocationStructureClaim
    {
        return $this->reviewTransition($claim, $reviewer, 'approved', $reason);
    }

    public function reject(LocationStructureClaim $claim, User $reviewer, string $reason): LocationStructureClaim
    {
        return $this->reviewTransition($claim, $reviewer, 'rejected', $reason);
    }

    private function reviewTransition(LocationStructureClaim $claim, User $reviewer, string $to, string $reason): LocationStructureClaim
    {
        return DB::transaction(function () use ($claim, $reviewer, $to, $reason): LocationStructureClaim {
            $locked = LocationStructureClaim::query()->lockForUpdate()->findOrFail($claim->id);

            if (! in_array($locked->status, self::OPEN_STATUSES, true)) {
                throw new \DomainException('Terminal structural claim cannot be reviewed again.');
            }

            $from = $locked->status;
            $audit = $locked->audit_log ?? [];
            $audit[] = [
                'from' => $from,
                'to' => $to,
                'actor_user_id' => $reviewer->id,
                'reason' => $reason,
                'at' => now()->toIso8601String(),
            ];

            $locked->forceFill([
                'status' => $to,
                'reviewed_by_user_id' => $reviewer->id,
                'review_reason' => $reason,
                'approved_at' => $to === 'approved' ? now() : null,
                'audit_log' => $audit,
            ])->save();

            if (in_array($to, ['approved', 'rejected'], true)) {
                app(PendingLocationGroupRequestService::class)->reconcileStructuralClaim($locked->fresh());
            }

            return $locked;
        });
    }

}
