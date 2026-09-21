<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\Setting;
use App\Models\User;
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

            return $locked;
        });
    }

}
