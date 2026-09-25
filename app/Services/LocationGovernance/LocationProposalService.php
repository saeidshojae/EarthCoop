<?php

namespace App\Services\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\ReferenceSettlement;
use App\Models\LocationType;
use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use DomainException;
use Illuminate\Support\Facades\DB;

class LocationProposalService
{
    private const OPEN_STATUSES = [
        LocationProposalStatus::Pending->value,
        LocationProposalStatus::ReadyForReview->value,
        LocationProposalStatus::NeedsEvidence->value,
    ];

    public function __construct(
        private readonly LocationDuplicateDetector $duplicateDetector,
        private readonly ResidenceService $residenceService,
        private readonly LocationProposalPolicy $proposalPolicy,
        private readonly LocationProposalSupportService $proposalSupportService,
        private readonly LocationStructureClaimPolicy $structureClaimPolicy,
    ) {
    }

    public function propose(User $proposer, Location $parent, LocationType $type, array $data, array $structuralClaims = []): LocationProposal|Location
    {
        $structuralClaims = $this->validatedStructuralClaimsForLocationParent($parent, $structuralClaims);

        if (! $this->proposalPolicy->allowsForResidence($parent, $type, $structuralClaims->all())) {
            throw new DomainException('Crowdsourced proposals are not permitted for this location type in the active schema.');
        }

        $canonicalName = $this->canonicalName($data);
        $normalizedName = $this->duplicateDetector->normalizeName($canonicalName);
        $duplicate = $this->duplicateDetector->findLikelyDuplicate($parent, $type, $canonicalName);

        if ($duplicate !== null) {
            return $duplicate;
        }

        $reusable = LocationProposal::query()
            ->where('parent_location_id', $parent->id)
            ->whereNull('parent_location_proposal_id')
            ->where('location_type_id', $type->id)
            ->where('normalized_name', $normalizedName)
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderBy('id')
            ->first();

        if ($reusable !== null) {
            return $reusable;
        }

        return LocationProposal::query()->create([
            'proposer_user_id' => $proposer->id,
            'parent_location_id' => $parent->id,
            'parent_location_proposal_id' => null,
            'location_schema_id' => $parent->location_schema_id,
            'location_type_id' => $type->id,
            'country_code' => $parent->country_code,
            'canonical_name' => $canonicalName,
            'normalized_name' => $normalizedName,
            'localized_names' => $data['localized_names'] ?? null,
            'status' => LocationProposalStatus::Pending,
            'metadata' => array_merge($data['metadata'] ?? [], [
                'structural_claim_ids' => $structuralClaims->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ]),
            'audit_log' => [],
        ]);
    }

    public function proposeUnderProposal(
        User $proposer,
        LocationProposal $parent,
        LocationType $type,
        array $data,
        array $structuralClaims = [],
    ): LocationProposal {
        $this->guardOpen($parent);
        $structuralClaims = $this->validatedStructuralClaimsForProposalParent($parent, $structuralClaims);

        if (! $this->proposalPolicy->allowsProposalParentForResidence($parent, $type, $structuralClaims->all())) {
            throw new DomainException('The requested location type is not a permitted crowdsourced child of this proposal.');
        }

        $referenceRoot = $parent->referenceSettlementRootProposal();

        $canonicalName = $this->canonicalName($data);
        $normalizedName = $this->duplicateDetector->normalizeName($canonicalName);

        $reusable = LocationProposal::query()
            ->whereNull('parent_location_id')
            ->where('parent_location_proposal_id', $parent->id)
            ->where('location_type_id', $type->id)
            ->where('normalized_name', $normalizedName)
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderBy('id')
            ->first();

        if ($reusable !== null) {
            return $reusable;
        }

        return LocationProposal::query()->create([
            'proposer_user_id' => $proposer->id,
            'parent_location_id' => null,
            'parent_location_proposal_id' => $parent->id,
            'location_schema_id' => $parent->location_schema_id,
            'location_type_id' => $type->id,
            'country_code' => $parent->country_code,
            'canonical_name' => $canonicalName,
            'normalized_name' => $normalizedName,
            'localized_names' => $data['localized_names'] ?? null,
            'status' => LocationProposalStatus::Pending,
            'metadata' => array_merge($data['metadata'] ?? [], [
                'structural_claim_ids' => $structuralClaims->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'reference_settlement_root_proposal_id' => $referenceRoot?->id,
                'reference_settlement_id' => $referenceRoot?->parent_reference_settlement_id,
            ]),
            'audit_log' => [],
        ]);
    }

    public function proposeUnderReferenceSettlement(
        User $proposer,
        ReferenceSettlement $settlement,
        LocationType $type,
        array $data,
        array $structuralClaims = [],
    ): LocationProposal {
        $structuralClaims = $this->validatedStructuralClaimsForReferenceSettlement($settlement, $structuralClaims);

        if (! $this->proposalPolicy->allowsReferenceSettlementParentForResidence($settlement, $type, $structuralClaims->all())) {
            throw new DomainException('The requested pending child is not allowed under this reference settlement.');
        }

        $anchor = app(IranSettlementAnchorResolver::class)->resolve($settlement);
        $canonicalName = $this->canonicalName($data);
        $normalizedName = $this->duplicateDetector->normalizeName($canonicalName);

        $reusable = LocationProposal::query()
            ->whereNull('parent_location_id')
            ->whereNull('parent_location_proposal_id')
            ->where('parent_reference_settlement_id', $settlement->id)
            ->where('location_type_id', $type->id)
            ->where('normalized_name', $normalizedName)
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderBy('id')->first();
        if ($reusable !== null) return $reusable;

        return LocationProposal::query()->create([
            'proposer_user_id' => $proposer->id,
            'parent_location_id' => null,
            'parent_location_proposal_id' => null,
            'parent_reference_settlement_id' => $settlement->id,
            'location_schema_id' => $anchor->location_schema_id,
            'location_type_id' => $type->id,
            'country_code' => $anchor->country_code,
            'canonical_name' => $canonicalName,
            'normalized_name' => $normalizedName,
            'localized_names' => $data['localized_names'] ?? null,
            'status' => LocationProposalStatus::Pending,
            'metadata' => array_merge($data['metadata'] ?? [], [
                'source' => 'reference_settlement_child',
                'reference_settlement_external_id' => $settlement->external_id,
                'structural_claim_ids' => $structuralClaims
                    ->pluck('id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
            ]),
            'audit_log' => [],
        ]);
    }

    public function rename(LocationProposal $proposal, User $reviewer, string $canonicalName, string $reason): void
    {
        $this->guardOpen($proposal);

        $canonicalName = $this->canonicalName(['canonical_name' => $canonicalName]);
        $normalizedName = $this->duplicateDetector->normalizeName($canonicalName);
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A review reason is required when renaming a location proposal.');
        }

        $proposal->refresh();
        $fromName = $proposal->canonical_name;
        $audit = $proposal->audit_log ?? [];
        $audit[] = [
            'action' => 'rename',
            'actor_user_id' => $reviewer->id,
            'reason' => $reason,
            'from_name' => $fromName,
            'to_name' => $canonicalName,
            'at' => now()->toIso8601String(),
        ];

        $proposal->canonical_name = $canonicalName;
        $proposal->normalized_name = $normalizedName;
        $proposal->audit_log = $audit;
        $proposal->save();
    }

    public function support(LocationProposal $proposal, User $user, array $evidence): void
    {
        $this->guardOpen($proposal);

        if (! $this->proposalPolicy->storedStructuralProvenanceIsValid($proposal)) {
            throw new DomainException('The proposal structural dependencies are no longer valid.');
        }

        $this->proposalSupportService->record($proposal, $user, $evidence);
    }

    public function requestMoreEvidence(LocationProposal $proposal, User $reviewer, string $reason): void
    {
        $this->guardOpen($proposal);
        $this->transition($proposal, LocationProposalStatus::NeedsEvidence, $reviewer, $reason, true);
    }

    public function approve(LocationProposal $proposal, User $reviewer, string $reason): Location
    {
        $this->guardOpen($proposal);

        if ($proposal->parent_reference_settlement_id !== null) {
            throw new DomainException('Promote the reference settlement to an operational Location before approving its neighborhood.');
        }

        return DB::transaction(function () use ($proposal, $reviewer, $reason): Location {
            $proposal->refresh();
            $parent = $proposal->parentLocation()->lockForUpdate()->first();

            if ($parent === null) {
                throw new DomainException('Approve the pending parent proposal before approving this descendant.');
            }

            $structuralClaimIds = collect($proposal->metadata['structural_claim_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $structuralClaims = LocationStructureClaim::query()
                ->whereIn('id', $structuralClaimIds)
                ->get();

            if (! $this->proposalPolicy->allowsForResidence($parent, $proposal->type, $structuralClaims->all())) {
                throw new DomainException('The proposal no longer satisfies the canonical location structure.');
            }

            if ($structuralClaimIds->isNotEmpty()
                && ($structuralClaims->count() !== $structuralClaimIds->count()
                    || $structuralClaims->contains(fn (LocationStructureClaim $claim): bool =>
                        (int) $claim->location_id !== (int) $parent->id
                        || ! in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence', 'approved'], true)
                    ))) {
                throw new DomainException('The structural claims supporting this proposal are no longer valid.');
            }

            $duplicate = $this->duplicateDetector->findLikelyDuplicate($parent, $proposal->type, $proposal->canonical_name);
            if ($duplicate !== null) {
                throw new DomainException('A matching canonical location already exists; merge the proposal instead.');
            }

            $location = Location::query()->create([
                'parent_id' => $parent->id,
                'location_schema_id' => $proposal->location_schema_id,
                'location_type_id' => $proposal->location_type_id,
                'country_code' => $proposal->country_code,
                'name' => $proposal->canonical_name,
                'canonical_name' => $proposal->canonical_name,
                'localized_names' => $proposal->localized_names,
                'level' => $proposal->type?->key,
                'status' => 'active',
                'provenance' => [
                    'source' => 'community_proposal',
                    'location_proposal_id' => $proposal->id,
                    'structural_claim_ids' => $structuralClaimIds->all(),
                ],
            ]);

            $this->reanchorOwnedStructuralClaims($proposal, $location);

            $proposal->resolved_location_id = $location->id;
            $proposal->approved_at = now();
            $proposal->save();
            $this->transition($proposal, LocationProposalStatus::Approved, $reviewer, $reason, true);
            $this->reanchorOpenChildren($proposal, $location);

            // A human-approved official location becomes part of EarthCoop's
            // official governance topology before residence/group reconciliation.
            // This keeps Location and GovernanceArea independent while ensuring
            // an approved crowdsourced official scope is no longer presented as pending.
            app(PendingLocationGroupRequestService::class)
                ->ensureApprovedOfficialTopology($proposal->fresh()->loadMissing('type'), $location);

            $this->residenceService->refreshPendingResidenceAnchorsForResolvedAncestry($location);
            $this->residenceService->resolvePendingResidenceIntents($proposal->fresh(), $location);
            app(PendingLocationGroupRequestService::class)->reconcileResolvedProposal($proposal->fresh(), $location);

            return $location;
        });
    }

    public function reject(LocationProposal $proposal, User $reviewer, string $reason): void
    {
        $this->guardOpen($proposal);
        $this->guardNoOpenChildren($proposal);
        $this->transition($proposal, LocationProposalStatus::Rejected, $reviewer, $reason, true);
        app(PendingLocationGroupRequestService::class)->rejectForProposal($proposal->fresh());
    }

    public function merge(LocationProposal $proposal, Location $existing, User $reviewer, string $reason): void
    {
        $this->guardOpen($proposal);

        DB::transaction(function () use ($proposal, $existing, $reviewer, $reason): void {
            if ((int) $existing->location_schema_id !== (int) $proposal->location_schema_id
                || (int) $existing->location_type_id !== (int) $proposal->location_type_id) {
                throw new DomainException('The merge target must use the same location schema and type as the proposal.');
            }

            if ($proposal->parent_location_id === null) {
                throw new DomainException('Resolve the pending parent proposal before merging this descendant.');
            }

            if ((int) $existing->parent_id !== (int) $proposal->parent_location_id) {
                throw new DomainException('The merge target must belong to the same canonical parent branch as the proposal.');
            }

            if (! $this->proposalPolicy->storedStructuralProvenanceIsValid($proposal)) {
                throw new DomainException('The proposal structural dependencies are no longer valid.');
            }

            $this->reanchorOwnedStructuralClaims($proposal, $existing);

            $proposal->resolved_location_id = $existing->id;
            $proposal->save();
            $this->transition($proposal, LocationProposalStatus::Merged, $reviewer, $reason, true);
            $this->reanchorOpenChildren($proposal, $existing);
            $this->residenceService->refreshPendingResidenceAnchorsForResolvedAncestry($existing);
            $this->residenceService->resolvePendingResidenceIntents($proposal->fresh(), $existing);
            app(PendingLocationGroupRequestService::class)->reconcileResolvedProposal($proposal->fresh(), $existing);
        });
    }

    private function reanchorOwnedStructuralClaims(LocationProposal $proposal, Location $target): void
    {
        $claims = LocationStructureClaim::query()
            ->with(['locationProposal.type'])
            ->where('location_proposal_id', $proposal->id)
            ->whereNull('location_id')
            ->whereIn('status', [...LocationStructureClaimService::OPEN_STATUSES, 'approved'])
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (LocationStructureClaim $claim): int =>
                count($this->structureClaimPolicy->requiredContextClaimTypes($claim))
            )
            ->values();

        if ($claims->isEmpty()) {
            return;
        }

        foreach ($claims as $claim) {
            if (! $this->structureClaimPolicy->dependenciesSatisfied(
                $claim,
                [...LocationStructureClaimService::OPEN_STATUSES, 'approved'],
            )) {
                throw new DomainException('A structural claim on the proposal has an invalid prerequisite.');
            }
        }

        $targetClaims = LocationStructureClaim::query()
            ->where('location_id', $target->id)
            ->whereNull('location_proposal_id')
            ->whereIn('status', [...LocationStructureClaimService::OPEN_STATUSES, 'approved'])
            ->lockForUpdate()
            ->get();

        foreach ($claims as $claim) {
            if ($targetClaims->contains('claim_type', $claim->claim_type)) {
                throw new DomainException('The merge target already has the same active structural claim; resolve the structural claim before merging.');
            }

            if ($targetClaims->pluck('claim_type')->intersect(
                $this->structureClaimPolicy->conflictingClaimTypes($claim->claim_type)
            )->isNotEmpty()) {
                throw new DomainException('The resolved target has a conflicting active structural claim.');
            }

            if (! $this->structureClaimPolicy->allowsClaimType($target, $claim->claim_type, $targetClaims)) {
                throw new DomainException('A structural claim on the proposal is not valid for the resolved target.');
            }

            $claim->forceFill([
                'location_id' => $target->id,
                'location_proposal_id' => null,
            ])->save();

            $targetClaims->push($claim->fresh());
        }
    }

    private function reanchorOpenChildren(LocationProposal $proposal, Location $resolvedParent): void
    {
        LocationProposal::query()
            ->where('parent_location_proposal_id', $proposal->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->update([
                'parent_location_id' => $resolvedParent->id,
                'parent_location_proposal_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function guardNoOpenChildren(LocationProposal $proposal): void
    {
        if ($proposal->childProposals()->whereIn('status', self::OPEN_STATUSES)->exists()) {
            throw new DomainException('Resolve or re-parent open descendant proposals before rejecting this proposal.');
        }
    }

    private function validatedStructuralClaimsForLocationParent(Location $parent, array $structuralClaims): \Illuminate\Support\Collection
    {
        return $this->validatedStructuralClaims(
            $structuralClaims,
            fn (LocationStructureClaim $claim): bool =>
                (int) $claim->location_id === (int) $parent->id
                && $claim->location_proposal_id === null
                && $claim->reference_settlement_id === null,
        );
    }

    private function validatedStructuralClaimsForProposalParent(LocationProposal $parent, array $structuralClaims): \Illuminate\Support\Collection
    {
        return $this->validatedStructuralClaims(
            $structuralClaims,
            fn (LocationStructureClaim $claim): bool =>
                (int) $claim->location_proposal_id === (int) $parent->id
                && $claim->location_id === null
                && $claim->reference_settlement_id === null,
        );
    }

    private function validatedStructuralClaimsForReferenceSettlement(ReferenceSettlement $settlement, array $structuralClaims): \Illuminate\Support\Collection
    {
        return $this->validatedStructuralClaims(
            $structuralClaims,
            fn (LocationStructureClaim $claim): bool =>
                (int) $claim->reference_settlement_id === (int) $settlement->id
                && $claim->location_id === null
                && $claim->location_proposal_id === null,
        );
    }

    private function validatedStructuralClaims(array $structuralClaims, callable $ownerMatches): \Illuminate\Support\Collection
    {
        $claims = collect($structuralClaims)
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim)
            ->unique('id')
            ->values();

        if ($claims->count() !== count($structuralClaims)
            || $claims->contains(fn (LocationStructureClaim $claim): bool =>
                ! $ownerMatches($claim)
                || ! in_array($claim->status, [...LocationStructureClaimService::OPEN_STATUSES, 'approved'], true)
            )) {
            throw new DomainException('The selected structural claims are no longer valid for this proposal path.');
        }

        foreach ($claims as $claim) {
            if (! $this->structureClaimPolicy->dependenciesSatisfied(
                $claim,
                [...LocationStructureClaimService::OPEN_STATUSES, 'approved'],
                $claims,
            )) {
                throw new DomainException('A structural claim prerequisite is not selected or approved for this proposal path.');
            }
        }

        return $claims;
    }

    private function canonicalName(array $data): string
    {
        $canonicalName = trim((string) ($data['canonical_name'] ?? ''));
        if ($this->duplicateDetector->normalizeName($canonicalName) === '') {
            throw new DomainException('A canonical location name is required.');
        }
        return $canonicalName;
    }

    private function transition(LocationProposal $proposal, LocationProposalStatus $to, User $actor, string $reason, bool $markReviewed = false): void
    {
        $proposal->refresh();
        $from = $proposal->status;
        $audit = $proposal->audit_log ?? [];
        $audit[] = [
            'from' => $from instanceof LocationProposalStatus ? $from->value : (string) $from,
            'to' => $to->value,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
            'at' => now()->toIso8601String(),
        ];
        $proposal->status = $to;
        $proposal->audit_log = $audit;
        if ($markReviewed) {
            $proposal->reviewed_by_user_id = $actor->id;
            $proposal->review_reason = $reason;
        }
        $proposal->save();
    }

    private function guardOpen(LocationProposal $proposal): void
    {
        $proposal->refresh();
        if (! in_array($proposal->status->value, self::OPEN_STATUSES, true)) {
            throw new DomainException('This location proposal is already resolved.');
        }
    }
}
