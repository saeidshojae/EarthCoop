<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\ReferenceSettlement;
use App\Models\Setting;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocationStructureClaimService
{
    public const OPEN_STATUSES = ['pending', 'ready_for_review', 'needs_evidence'];
    private const ACTIVE_STATUSES = ['pending', 'ready_for_review', 'needs_evidence', 'approved'];

    private const CONFLICTS = [
        'single_urban_region' => ['no_urban_region'],
        'no_urban_region' => ['single_urban_region'],
        'single_neighborhood' => ['no_neighborhood'],
        'no_neighborhood' => ['single_neighborhood'],
    ];

    public function findOrCreateOpenClaim(Location $location, string $type, User $proposer): LocationStructureClaim
    {
        return DB::transaction(function () use ($location, $type, $proposer): LocationStructureClaim {
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);
            if ($location->status !== 'active') {
                throw new DomainException('Inactive location cannot accept structural claims.');
            }

            $contextClaims = LocationStructureClaim::query()
                ->where('location_id', $location->id)
                ->whereNull('location_proposal_id')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get();

            if (! app(LocationStructureClaimPolicy::class)->allowsClaimType($location, $type, $contextClaims)) {
                throw new DomainException('Structural claim type is not allowed for this location type.');
            }

            $this->assertNoConflictingClaim($contextClaims, $type);

            $claim = $contextClaims->firstWhere('claim_type', $type);
            if ($claim instanceof LocationStructureClaim) {
                return $claim;
            }

            $candidate = new LocationStructureClaim([
                'location_id' => $location->id,
                'claim_type' => $type,
            ]);
            $candidate->setRelation('location', $location);

            return LocationStructureClaim::query()->create([
                'location_id' => $location->id,
                'location_proposal_id' => null,
                'claim_type' => $type,
                'status' => 'pending',
                'proposer_user_id' => $proposer->id,
                'metadata' => $this->dependencyMetadata($candidate, $contextClaims),
                'audit_log' => [],
            ]);
        });
    }

    public function findOrCreateOpenClaimForProposal(
        LocationProposal $proposal,
        string $type,
        User $proposer,
    ): LocationStructureClaim {
        $status = $proposal->status instanceof \BackedEnum
            ? $proposal->status->value
            : (string) $proposal->status;
        if (! in_array($status, self::OPEN_STATUSES, true)) {
            throw new DomainException('Terminal location proposal cannot accept structural claims.');
        }

        return DB::transaction(function () use ($proposal, $type, $proposer): LocationStructureClaim {
            $proposal = LocationProposal::query()->with('type')->lockForUpdate()->findOrFail($proposal->id);
            $lockedStatus = $proposal->status instanceof \BackedEnum
                ? $proposal->status->value
                : (string) $proposal->status;
            if (! in_array($lockedStatus, self::OPEN_STATUSES, true)) {
                throw new DomainException('Terminal location proposal cannot accept structural claims.');
            }

            $contextClaims = LocationStructureClaim::query()
                ->where('location_proposal_id', $proposal->id)
                ->whereNull('location_id')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get();

            $policy = app(LocationStructureClaimPolicy::class);
            if (! $policy->allowsProposalClaimType($proposal, $type, $contextClaims)) {
                throw new DomainException('Structural claim type is not allowed for this proposed location type.');
            }

            $this->assertNoConflictingClaim($contextClaims, $type);

            $claim = $contextClaims->firstWhere('claim_type', $type);
            if ($claim instanceof LocationStructureClaim) {
                return $claim;
            }

            $candidate = new LocationStructureClaim([
                'location_proposal_id' => $proposal->id,
                'claim_type' => $type,
            ]);
            $candidate->setRelation('locationProposal', $proposal);

            return LocationStructureClaim::query()->create([
                'location_id' => null,
                'location_proposal_id' => $proposal->id,
                'claim_type' => $type,
                'status' => 'pending',
                'proposer_user_id' => $proposer->id,
                'metadata' => array_merge(
                    ['source' => 'pending_location_structure'],
                    $this->dependencyMetadata($candidate, $contextClaims),
                ),
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
            throw new DomainException('Only no-neighborhood is exposed for a reference settlement.');
        }

        $claimable = (
            in_array($settlement->classification, ['unverified_settlement', 'needs_review'], true)
            && $settlement->residential_eligibility === 'unverified'
        ) || (
            $settlement->classification === 'verified_residential_village'
            && $settlement->residential_eligibility === 'verified'
        );

        if (! $claimable || $settlement->governance_authorized || $settlement->operational_promotion_allowed) {
            throw new DomainException('This reference settlement cannot accept an open structural claim.');
        }

        return DB::transaction(function () use ($settlement, $type, $proposer): LocationStructureClaim {
            return LocationStructureClaim::query()
                ->where('reference_settlement_id', $settlement->id)
                ->where('claim_type', $type)
                ->whereIn('status', array_merge(self::OPEN_STATUSES, ['approved']))
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
            $this->lockOwnerForClaim($claim);

            if (! in_array($claim->status, self::OPEN_STATUSES, true)) {
                return;
            }

            if (! app(LocationStructureClaimPolicy::class)->dependenciesSatisfied(
                $claim,
                self::ACTIVE_STATUSES,
            )) {
                throw new DomainException('Structural claim prerequisite is no longer valid.');
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
            $locked = LocationStructureClaim::query()
                ->with(['location', 'locationProposal'])
                ->lockForUpdate()
                ->findOrFail($claim->id);

            if (! in_array($locked->status, self::OPEN_STATUSES, true)) {
                throw new DomainException('Terminal structural claim cannot be reviewed again.');
            }

            $this->lockOwnerForClaim($locked);
            $policy = app(LocationStructureClaimPolicy::class);
            if ($to === 'approved'
                && ! $policy->dependenciesSatisfied($locked, ['approved'])) {
                throw new DomainException('Approve the prerequisite structural claim before approving this dependent claim.');
            }

            $dependents = collect();
            if ($to === 'rejected') {
                $dependentTypes = $policy->dependentClaimTypes($locked);
                if ($dependentTypes !== []) {
                    $dependents = $this->ownerClaimQuery($locked)
                        ->whereIn('claim_type', $dependentTypes)
                        ->whereIn('status', self::ACTIVE_STATUSES)
                        ->lockForUpdate()
                        ->get();

                    if ($dependents->contains(fn (LocationStructureClaim $dependent): bool => $dependent->status === 'approved')) {
                        throw new DomainException('An approved dependent structural claim must be resolved before rejecting its prerequisite.');
                    }

                    foreach ($dependents as $dependent) {
                        $audit = $dependent->audit_log ?? [];
                        $audit[] = [
                            'from' => $dependent->status,
                            'to' => 'rejected',
                            'actor_user_id' => $reviewer->id,
                            'reason' => 'prerequisite_rejected:'.$locked->id.' — '.$reason,
                            'at' => now()->toIso8601String(),
                        ];
                        $dependent->forceFill([
                            'status' => 'rejected',
                            'reviewed_by_user_id' => $reviewer->id,
                            'review_reason' => 'Prerequisite structural claim #'.$locked->id.' was rejected: '.$reason,
                            'approved_at' => null,
                            'audit_log' => $audit,
                        ])->save();
                    }
                }
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
                $pending = app(PendingLocationGroupRequestService::class);
                $residence = app(ResidenceService::class);
                $reviewed = $locked->fresh();

                $pending->reconcileStructuralClaim($reviewed);
                if ($to === 'rejected') {
                    $residence->cancelPendingIntentsDependingOnStructuralClaim($reviewed);
                }

                foreach ($dependents as $dependent) {
                    $dependent = $dependent->fresh();
                    $pending->reconcileStructuralClaim($dependent);
                    $residence->cancelPendingIntentsDependingOnStructuralClaim($dependent);
                }
            }

            return $locked->fresh();
        });
    }

    private function lockOwnerForClaim(LocationStructureClaim $claim): void
    {
        if ($claim->location_id !== null) {
            Location::query()->whereKey($claim->location_id)->lockForUpdate()->firstOrFail();

            return;
        }

        if ($claim->location_proposal_id !== null) {
            LocationProposal::query()->whereKey($claim->location_proposal_id)->lockForUpdate()->firstOrFail();

            return;
        }

        // Reference-settlement claims have no conditional owner-local prerequisites.
    }

    private function assertNoConflictingClaim(Collection $contextClaims, string $type): void
    {
        if ($contextClaims->pluck('claim_type')->intersect(self::CONFLICTS[$type] ?? [])->isNotEmpty()) {
            throw new DomainException('Conflicting structural claim already exists for this location tier.');
        }
    }

    private function dependencyMetadata(LocationStructureClaim $candidate, Collection $contextClaims): array
    {
        $required = app(LocationStructureClaimPolicy::class)->requiredContextClaimTypes($candidate);
        if ($required === []) {
            return [];
        }

        $ids = $contextClaims
            ->whereIn('claim_type', $required)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $ids === [] ? [] : ['depends_on_claim_ids' => $ids];
    }

    private function ownerClaimQuery(LocationStructureClaim $claim)
    {
        $query = LocationStructureClaim::query();

        if ($claim->location_id !== null) {
            return $query->where('location_id', $claim->location_id)->whereNull('location_proposal_id');
        }

        if ($claim->location_proposal_id !== null) {
            return $query->where('location_proposal_id', $claim->location_proposal_id)->whereNull('location_id');
        }

        return $query->whereRaw('1 = 0');
    }
}
