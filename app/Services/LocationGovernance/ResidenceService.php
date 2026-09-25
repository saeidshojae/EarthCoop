<?php

namespace App\Services\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Exceptions\ResidenceTransferLimitExceeded;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\PendingResidenceIntent;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidenceService
{
    public function __construct(
        private readonly ResidenceTransferPolicy $transferPolicy,
        private readonly GovernanceResolver $governanceResolver,
        private readonly CanonicalGroupMembershipReconciler $groupMembershipReconciler,
        private readonly CommunityAreaService $communityAreaService,
        private readonly LocationProposalSupportService $proposalSupportService,
    ) {
    }

    public function setInitialPrimaryResidence(User $user, Location $location, array $evidence, array $structuralClaims = []): UserLocationRelationship
    {
        return DB::transaction(function () use ($user, $location, $evidence, $structuralClaims): UserLocationRelationship {
            $claims = $this->validatedStructuralClaimsForResidence($location, $structuralClaims);
            $existing = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ((int) $existing->location_id !== (int) $location->id) {
                    return $existing;
                }
                if ($claims->isNotEmpty()) {
                    $metadata = $existing->metadata ?? [];
                    $metadata['structural_claim_ids'] = collect($metadata['structural_claim_ids'] ?? [])
                        ->merge($claims->pluck('id'))
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                    $existing->forceFill(['metadata' => $metadata])->save();
                }

                foreach ($claims->whereIn('status', LocationStructureClaimService::OPEN_STATUSES) as $claim) {
                    app(LocationStructureClaimService::class)->recordCommittedSupport($claim, $user, [
                        'source' => 'residence_commit',
                        'relationship_id' => $existing->id,
                    ]);
                }
                $this->reconcileCanonicalGroupsIfEnabled($user, $location, $claims);
                return $existing;
            }

            $relationship = UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'relationship_type' => 'primary_residence',
                'started_at' => now(),
                'ended_at' => null,
                'evidence' => $evidence,
                'metadata' => $claims->isEmpty() ? null : [
                    'structural_claim_ids' => $claims->pluck('id')->values()->all(),
                ],
                'explicit_transfer' => false,
                'transfer_override' => false,
            ]);

            foreach ($claims->whereIn('status', LocationStructureClaimService::OPEN_STATUSES) as $claim) {
                app(LocationStructureClaimService::class)->recordCommittedSupport($claim, $user, [
                    'source' => 'residence_commit',
                    'relationship_id' => $relationship->id,
                ]);
            }

            $this->reconcileCanonicalGroupsIfEnabled($user, $location, $claims);

            return $relationship;
        });
    }

    public function refreshPrimaryResidenceStructuralClaims(
        User $user,
        Location $location,
        array $structuralClaims,
    ): UserLocationRelationship {
        return DB::transaction(function () use ($user, $location, $structuralClaims): UserLocationRelationship {
            $claims = $this->validatedStructuralClaimsForResidence($location, $structuralClaims);
            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $current->location_id !== (int) $location->id) {
                throw ValidationException::withMessages([
                    'location_id' => 'محل سکونت جاری با مسیر انتخاب‌شده هم‌خوان نیست.',
                ]);
            }

            if ($claims->isNotEmpty()) {
                $metadata = $current->metadata ?? [];
                $metadata['structural_claim_ids'] = collect($metadata['structural_claim_ids'] ?? [])
                    ->merge($claims->pluck('id'))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $current->forceFill(['metadata' => $metadata])->save();
            }

            foreach ($claims->whereIn('status', LocationStructureClaimService::OPEN_STATUSES) as $claim) {
                app(LocationStructureClaimService::class)->recordCommittedSupport($claim, $user, [
                    'source' => 'residence_commit',
                    'relationship_id' => $current->id,
                ]);
            }

            $this->reconcileCanonicalGroupsIfEnabled($user, $location, $claims);

            return $current;
        });
    }

    public function setPendingResidenceIntent(
        User $user,
        LocationProposal $proposal,
        array $metadata = [],
        array $structuralClaims = [],
    ): PendingResidenceIntent {
        if (! in_array($proposal->status, [
            LocationProposalStatus::Pending,
            LocationProposalStatus::ReadyForReview,
            LocationProposalStatus::NeedsEvidence,
        ], true)) {
            throw ValidationException::withMessages([
                'location_proposal_id' => 'این پیشنهاد مکان دیگر در وضعیت قابل انتخاب نیست.',
            ]);
        }

        return DB::transaction(function () use ($user, $proposal, $metadata, $structuralClaims): PendingResidenceIntent {
            $proposalChainIds = collect();
            $cursor = $proposal->loadMissing('parentProposal');
            $visited = [];
            while ($cursor !== null && ! isset($visited[$cursor->id])) {
                $visited[$cursor->id] = true;
                $proposalChainIds->push((int) $cursor->id);
                $cursor = $cursor->parentProposal()->first();
            }

            $claims = collect($structuralClaims)->map(function ($claim) use ($proposalChainIds): LocationStructureClaim {
                $locked = LocationStructureClaim::query()->lockForUpdate()->findOrFail($claim->id);
                if (
                    $locked->location_proposal_id === null
                    || ! $proposalChainIds->contains((int) $locked->location_proposal_id)
                    || (! in_array($locked->status, LocationStructureClaimService::OPEN_STATUSES, true) && $locked->status !== 'approved')
                ) {
                    throw ValidationException::withMessages([
                        'location_structure_claim_ids' => 'ادعای ساختاری انتخاب‌شده دیگر برای مسیر پیشنهادی محل سکونت قابل استفاده نیست.',
                    ]);
                }

                return $locked;
            })->values();

            $structurePolicy = app(LocationStructureClaimPolicy::class);
            foreach ($claims as $claim) {
                if (! $structurePolicy->dependenciesSatisfied(
                    $claim,
                    array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']),
                    $claims,
                )) {
                    throw ValidationException::withMessages([
                        'location_structure_claim_ids' => 'پیش‌نیاز ادعای ساختاری مسیر پیشنهادی انتخاب یا تأیید نشده است.',
                    ]);
                }
            }

            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'ابتدا باید یک مکان تأییدشده به‌عنوان مبنای محل سکونت مشخص شود.',
                ]);
            }

            $canonicalAnchor = $proposal->nearestCanonicalParent();
            if ($canonicalAnchor === null || (int) $canonicalAnchor->id !== (int) $current->location_id) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'پیشنهاد مکان باید ادامهٔ همان مسیر محل سکونت تأییدشده باشد.',
                ]);
            }

            $at = now();
            $this->cancelPendingIntentRows($user, 'replaced_by_new_pending_residence', $at);

            if ($claims->isNotEmpty()) {
                $metadata['structural_claim_ids'] = $claims->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            }

            $intent = PendingResidenceIntent::query()->create([
                'user_id' => $user->id,
                'anchor_relationship_id' => $current->id,
                'location_proposal_id' => $proposal->id,
                'status' => 'pending',
                'selected_at' => $at,
                'metadata' => $metadata,
            ]);

            $this->proposalSupportService->record($proposal, $user, [
                'source' => 'residence_commit',
                'pending_residence_intent_id' => $intent->id,
                'anchor_relationship_id' => $current->id,
            ]);

            foreach ($claims->whereIn('status', LocationStructureClaimService::OPEN_STATUSES) as $claim) {
                app(LocationStructureClaimService::class)->recordCommittedSupport($claim, $user, [
                    'source' => 'residence_commit',
                    'pending_residence_intent_id' => $intent->id,
                ]);
            }

            return $intent;
        });
    }

    public function setPendingReferenceSettlementIntent(
        User $user,
        ReferenceSettlementResidenceClaim $claim,
        Location $anchor,
        array $metadata = [],
    ): PendingResidenceIntent {
        return DB::transaction(function () use ($user, $claim, $anchor, $metadata): PendingResidenceIntent {
            $lockedClaim = ReferenceSettlementResidenceClaim::query()
                ->with('settlement')
                ->whereKey($claim->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedClaim->status, ['pending', 'needs_evidence', 'residential_evidence_verified'], true)) {
                throw ValidationException::withMessages([
                    'reference_settlement_external_id' => 'درخواست سکونت این آبادی دیگر در وضعیت قابل استفاده برای ثبت‌نام نیست.',
                ]);
            }

            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current === null || (int) $current->location_id !== (int) $anchor->id) {
                throw ValidationException::withMessages([
                    'reference_settlement_external_id' => 'مبنای canonical محل سکونت با والد تطبیق‌یافتهٔ این آبادی هم‌خوان نیست.',
                ]);
            }

            $at = now();
            $this->cancelPendingIntentRows($user, 'replaced_by_reference_settlement_claim', $at);

            return PendingResidenceIntent::query()->create([
                'user_id' => $user->id,
                'anchor_relationship_id' => $current->id,
                'location_proposal_id' => null,
                'reference_settlement_residence_claim_id' => $lockedClaim->id,
                'status' => 'pending',
                'selected_at' => $at,
                'metadata' => array_merge($metadata, [
                    'reference_settlement_external_id' => $lockedClaim->settlement?->external_id,
                    'reference_settlement_parent_external_id' => $lockedClaim->settlement?->parent_external_id,
                ]),
            ]);
        });
    }

    public function setPendingReferenceSettlementProposalIntent(
        User $user,
        ReferenceSettlementResidenceClaim $claim,
        LocationProposal $proposal,
        Location $anchor,
        array $metadata = [],
    ): PendingResidenceIntent {
        return DB::transaction(function () use ($user, $claim, $proposal, $anchor, $metadata): PendingResidenceIntent {
            $lockedClaim = ReferenceSettlementResidenceClaim::query()
                ->with('settlement')->whereKey($claim->id)->where('user_id', $user->id)
                ->lockForUpdate()->firstOrFail();
            $lockedProposal = LocationProposal::query()->with(['type', 'parentProposal.type'])->whereKey($proposal->id)
                ->lockForUpdate()->firstOrFail();

            $referenceRoot = $lockedProposal->referenceSettlementRootProposal()?->loadMissing('type');
            $chainOpen = true;
            $cursor = $lockedProposal;
            $visited = [];
            while ($cursor !== null) {
                if (isset($visited[$cursor->id])) {
                    $chainOpen = false;
                    break;
                }
                $visited[$cursor->id] = true;
                if (! in_array($cursor->status, [
                    LocationProposalStatus::Pending,
                    LocationProposalStatus::ReadyForReview,
                    LocationProposalStatus::NeedsEvidence,
                ], true)) {
                    $chainOpen = false;
                    break;
                }
                if ($cursor->parent_reference_settlement_id !== null) {
                    break;
                }
                $cursor = $cursor->parentProposal()->with('type')->first();
            }

            $structuralClaimIds = collect($metadata['structural_claim_ids'] ?? [])
                ->merge($referenceRoot?->metadata['structural_claim_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();
            $referenceStructuralClaims = LocationStructureClaim::query()
                ->whereIn('id', $structuralClaimIds)
                ->where('reference_settlement_id', $lockedClaim->reference_settlement_id)
                ->whereIn('status', [...LocationStructureClaimService::OPEN_STATUSES, 'approved'])
                ->get();
            $claimTypes = $referenceStructuralClaims->pluck('claim_type');
            $rootType = $referenceRoot?->type?->key;
            $validRoot = ($rootType === 'neighborhood' && ! $claimTypes->contains('no_neighborhood'))
                || ($rootType === 'street' && $claimTypes->contains('no_neighborhood'));

            if (! in_array($lockedClaim->status, ['pending', 'needs_evidence', 'residential_evidence_verified'], true)
                || ! $chainOpen
                || $referenceRoot === null
                || (int) $referenceRoot->parent_reference_settlement_id !== (int) $lockedClaim->reference_settlement_id
                || ! $validRoot) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'جزئیات انتخاب‌شده ادامهٔ معتبر همین آبادی مرجع نیست.',
                ]);
            }

            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')->lockForUpdate()->first();
            if ($current === null || (int) $current->location_id !== (int) $anchor->id) {
                throw ValidationException::withMessages([
                    'reference_settlement_external_id' => 'مبنای canonical محل سکونت با والد تطبیق‌یافتهٔ این آبادی هم‌خوان نیست.',
                ]);
            }

            $at = now();
            $this->cancelPendingIntentRows($user, 'replaced_by_reference_settlement_neighborhood', $at);
            $intent = PendingResidenceIntent::query()->create([
                'user_id' => $user->id,
                'anchor_relationship_id' => $current->id,
                'location_proposal_id' => $lockedProposal->id,
                'reference_settlement_residence_claim_id' => $lockedClaim->id,
                'status' => 'pending',
                'selected_at' => $at,
                'metadata' => array_merge($metadata, [
                    'reference_settlement_external_id' => $lockedClaim->settlement?->external_id,
                    'reference_settlement_parent_external_id' => $lockedClaim->settlement?->parent_external_id,
                    'reference_settlement_root_proposal_id' => $referenceRoot->id,
                    'reference_settlement_exact_type' => $lockedProposal->type?->key,
                    'structural_claim_ids' => $referenceStructuralClaims
                        ->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ]),
            ]);
            $this->proposalSupportService->record($lockedProposal, $user, [
                'source' => 'residence_commit',
                'pending_residence_intent_id' => $intent->id,
                'anchor_relationship_id' => $current->id,
            ]);
            return $intent;
        });
    }

    public function cancelPendingReferenceSettlementIntent(
        ReferenceSettlementResidenceClaim $claim,
        string $reason,
    ): int {
        return DB::transaction(function () use ($claim, $reason): int {
            $at = now();
            $intents = PendingResidenceIntent::query()
                ->where('reference_settlement_residence_claim_id', $claim->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($intents as $intent) {
                $this->cancelIntent($intent, $reason, $at);
            }

            return $intents->count();
        });
    }

    public function cancelPendingIntentsDependingOnStructuralClaim(LocationStructureClaim $claim): int
    {
        if ($claim->status !== 'rejected') {
            return 0;
        }

        return DB::transaction(function () use ($claim): int {
            $intents = PendingResidenceIntent::query()
                ->where('status', 'pending')
                ->where(function ($query) use ($claim): void {
                    $query->whereJsonContains('metadata->structural_claim_ids', (int) $claim->id)
                        ->orWhereHas('anchorRelationship', fn ($anchor) =>
                            $anchor->whereJsonContains('metadata->structural_claim_ids', (int) $claim->id)
                        )
                        ->orWhereHas('locationProposal', fn ($proposal) =>
                            $proposal->whereJsonContains('metadata->structural_claim_ids', (int) $claim->id)
                        );
                })
                ->lockForUpdate()
                ->get();

            $at = now();
            foreach ($intents as $intent) {
                $this->cancelIntent($intent, 'structural_claim_rejected', $at);
            }

            return $intents->count();
        });
    }

    public function clearPendingResidenceIntent(User $user, string $reason): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $this->cancelPendingIntentRows($user, $reason, now());
        });
    }

    public function refreshPendingResidenceAnchorsForResolvedAncestry(Location $resolvedLocation): int
    {
        return DB::transaction(function () use ($resolvedLocation): int {
            $updated = 0;
            $at = now();

            $intents = PendingResidenceIntent::query()
                ->where('status', 'pending')
                ->with('locationProposal')
                ->lockForUpdate()
                ->get();

            foreach ($intents as $intent) {
                $deepest = $intent->locationProposal?->loadMissing('type');
                $typeKey = $deepest?->type?->key;
                if (
                    $deepest === null
                    || ! in_array($typeKey, ['city', 'rural_district', 'urban_region', 'village', 'neighborhood'], true)
                    || (int) ($deepest->nearestCanonicalParent()?->id ?? 0) !== (int) $resolvedLocation->id
                ) {
                    continue;
                }

                $current = UserLocationRelationship::query()
                    ->where('user_id', $intent->user_id)
                    ->where('relationship_type', 'primary_residence')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if ($current === null || (int) $current->id !== (int) $intent->anchor_relationship_id) {
                    continue;
                }

                if ((int) $current->location_id === (int) $resolvedLocation->id) {
                    continue;
                }

                $current->forceFill(['ended_at' => $at])->save();
                $next = UserLocationRelationship::query()->create([
                    'user_id' => $intent->user_id,
                    'location_id' => $resolvedLocation->id,
                    'relationship_type' => 'primary_residence',
                    'started_at' => $at,
                    'ended_at' => null,
                    'evidence' => [
                        'source' => 'pending_location_ancestor_resolution',
                        'proposal_id' => $deepest->id,
                    ],
                    'explicit_transfer' => false,
                    'transfer_override' => false,
                    'metadata' => [
                        'pending_residence_intent_id' => $intent->id,
                        'refined_from_relationship_id' => $current->id,
                    ],
                ]);

                $intent->forceFill(['anchor_relationship_id' => $next->id])->save();
                $updated++;
                $this->reconcileCanonicalGroupsIfEnabled(User::query()->findOrFail($intent->user_id));
            }

            return $updated;
        });
    }

    public function resolvePendingResidenceIntents(LocationProposal $proposal, Location $resolvedLocation): int
    {
        return DB::transaction(function () use ($proposal, $resolvedLocation): int {
            $resolvedCount = 0;
            $at = now();

            $intents = PendingResidenceIntent::query()
                ->where('location_proposal_id', $proposal->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($intents as $intent) {
                $anchor = UserLocationRelationship::query()
                    ->whereKey($intent->anchor_relationship_id)
                    ->where('user_id', $intent->user_id)
                    ->where('relationship_type', 'primary_residence')
                    ->lockForUpdate()
                    ->first();

                $current = UserLocationRelationship::query()
                    ->where('user_id', $intent->user_id)
                    ->where('relationship_type', 'primary_residence')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if ($anchor === null || $current === null || (int) $current->id !== (int) $anchor->id) {
                    $this->cancelIntent($intent, 'stale_anchor', $at);
                    continue;
                }

                $resolvedStructuralClaimIds = collect(($intent->metadata ?? [])['structural_claim_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($resolvedStructuralClaimIds !== [] && LocationStructureClaim::query()
                    ->whereIn('id', $resolvedStructuralClaimIds)
                    ->where('status', 'rejected')
                    ->exists()) {
                    $this->cancelIntent($intent, 'structural_claim_rejected', $at);
                    continue;
                }

                if ((int) $current->location_id !== (int) $resolvedLocation->id) {
                    $current->forceFill(['ended_at' => $at])->save();

                    UserLocationRelationship::query()->create([
                        'user_id' => $intent->user_id,
                        'location_id' => $resolvedLocation->id,
                        'relationship_type' => 'primary_residence',
                        'started_at' => $at,
                        'ended_at' => null,
                        'evidence' => [
                            'source' => 'location_proposal_resolution',
                            'proposal_id' => $proposal->id,
                        ],
                        'explicit_transfer' => false,
                        'transfer_override' => false,
                        'changed_by_user_id' => $proposal->reviewed_by_user_id,
                        'change_reason' => 'location_proposal_resolution',
                        'metadata' => array_filter([
                            'pending_residence_intent_id' => $intent->id,
                            'refined_from_relationship_id' => $anchor->id,
                            'structural_claim_ids' => $resolvedStructuralClaimIds !== [] ? $resolvedStructuralClaimIds : null,
                        ], fn ($value) => $value !== null),
                    ]);
                } elseif ($resolvedStructuralClaimIds !== []) {
                    $currentMetadata = $current->metadata ?? [];
                    $currentMetadata['structural_claim_ids'] = collect($currentMetadata['structural_claim_ids'] ?? [])
                        ->merge($resolvedStructuralClaimIds)
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                    $current->forceFill(['metadata' => $currentMetadata])->save();
                }

                $intent->forceFill([
                    'status' => 'resolved',
                    'resolved_location_id' => $resolvedLocation->id,
                    'resolved_at' => $at,
                ])->save();

                $resolvedCount++;
                $this->reconcileCanonicalGroupsIfEnabled(User::query()->findOrFail($intent->user_id));
            }

            return $resolvedCount;
        });
    }


    public function reanchorPrimaryResidenceIfVerifiedReferenceEquivalent(
        User $user,
        Location $to,
        array $evidence = [],
    ): ?UserLocationRelationship {
        return DB::transaction(function () use ($user, $to, $evidence): ?UserLocationRelationship {
            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                return null;
            }
            if ((int) $current->location_id === (int) $to->id) {
                return $current;
            }

            $from = Location::query()->find($current->location_id);
            if (! $from instanceof Location || ! $this->verifiedReferenceEquivalent($from, $to)) {
                return null;
            }

            $at = now();
            $current->forceFill(['ended_at' => $at])->save();

            $next = UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $to->id,
                'relationship_type' => 'primary_residence',
                'started_at' => $at,
                'ended_at' => null,
                'evidence' => array_merge($evidence, ['source' => 'reference_dataset_identity_upgrade']),
                'explicit_transfer' => false,
                'transfer_override' => false,
                'change_reason' => 'reference_dataset_identity_upgrade',
                'metadata' => [
                    'refined_from_relationship_id' => $current->id,
                ],
            ]);

            $this->cancelPendingIntentRows($user, 'reference_dataset_identity_upgrade', $at);
            $this->reconcileCanonicalGroupsIfEnabled($user, $to);

            return $next;
        });
    }

    private function verifiedReferenceEquivalent(Location $from, Location $to): bool
    {
        if ($from->country_code !== 'IR' || $to->country_code !== 'IR') {
            return false;
        }

        $source = config('iran_v1_v2_crosswalk.source', 'earthcoop-reference');
        $v1Version = config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1');
        $v2Version = config('iran_v1_v2_crosswalk.v2_dataset_version', 'v2');

        $fromV1 = LocationExternalId::query()
            ->where('location_id', $from->id)
            ->where('source', $source)
            ->where('dataset_version', $v1Version)
            ->value('external_id');
        $toV2 = LocationExternalId::query()
            ->where('location_id', $to->id)
            ->where('source', $source)
            ->where('dataset_version', $v2Version)
            ->value('external_id');

        if (! is_string($fromV1) || ! is_string($toV2)) {
            return false;
        }

        $mapping = config('iran_v1_v2_crosswalk.mappings.'.$fromV1);

        return is_array($mapping)
            && ($mapping['status'] ?? null) === 'verified_identity'
            && (string) ($mapping['v2'] ?? '') === $toV2;
    }

    public function transferPrimaryResidence(
        User $user,
        Location $to,
        User $actor,
        string $reason,
        bool $override = false,
        array $structuralClaims = [],
    ): UserLocationRelationship {
        $at = now();

        if (! $override && ! $this->transferPolicy->allowsExplicitTransfer($user, $at)) {
            throw new ResidenceTransferLimitExceeded('The rolling primary-residence transfer limit has been reached.');
        }

        return DB::transaction(function () use ($user, $to, $actor, $reason, $override, $at, $structuralClaims): UserLocationRelationship {
            $claims = $this->validatedStructuralClaimsForResidence($to, $structuralClaims);

            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->forceFill(['ended_at' => $at])->save();
            }

            $this->cancelPendingIntentRows($user, 'primary_residence_transferred', $at);

            $relationship = UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $to->id,
                'relationship_type' => 'primary_residence',
                'started_at' => $at,
                'ended_at' => null,
                'evidence' => [],
                'explicit_transfer' => true,
                'transfer_override' => $override,
                'changed_by_user_id' => $actor->id,
                'change_reason' => $reason,
                'metadata' => $claims->isEmpty() ? null : [
                    'structural_claim_ids' => $claims->pluck('id')->values()->all(),
                ],
            ]);

            foreach ($claims->whereIn('status', LocationStructureClaimService::OPEN_STATUSES) as $claim) {
                app(LocationStructureClaimService::class)->recordCommittedSupport($claim, $user, [
                    'source' => 'residence_commit',
                    'relationship_id' => $relationship->id,
                ]);
            }

            $this->reconcileCanonicalGroupsIfEnabled($user, $to, $claims);

            return $relationship;
        });
    }

    public function currentPrimaryResidence(User $user): ?UserLocationRelationship
    {
        return UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->with('location')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    public function officialGovernanceAreasFor(User $user): Collection
    {
        $primaryResidence = $this->currentPrimaryResidence($user);

        if ($primaryResidence === null || $primaryResidence->location === null) {
            return collect();
        }

        $areas = $this->governanceResolver->officialAreasForResidence($primaryResidence->location);
        $structuralClaimIds = collect(($primaryResidence->metadata ?? [])['structural_claim_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($structuralClaimIds->isEmpty() || $areas->isEmpty()) {
            return $areas;
        }

        $hasPendingBaseClaim = LocationStructureClaim::query()
            ->whereIn('id', $structuralClaimIds)
            ->where('location_id', $primaryResidence->location_id)
            ->where('claim_type', 'no_neighborhood')
            ->where('status', '<>', 'approved')
            ->exists();

        if (! $hasPendingBaseClaim) {
            return $areas;
        }

        $base = $areas->first();
        $baseMapsCurrentResidence = $base !== null
            && $base->locations()->whereKey($primaryResidence->location_id)->exists();

        return $baseMapsCurrentResidence ? $areas->skip(1)->values() : $areas;
    }

    private function validatedStructuralClaimsForResidence(Location $location, array $structuralClaims): Collection
    {
        $claims = collect($structuralClaims)->map(function ($claim) use ($location): LocationStructureClaim {
            $locked = LocationStructureClaim::query()->lockForUpdate()->findOrFail($claim->id);
            $matchesPath = (int) $locked->location_id === (int) $location->id;
            $cursor = $location;
            while (! $matchesPath && $cursor->parent_id !== null) {
                $cursor = $cursor->parent()->first();
                if ($cursor === null) break;
                $matchesPath = (int) $locked->location_id === (int) $cursor->id;
            }
            if (! $matchesPath || (! in_array($locked->status, LocationStructureClaimService::OPEN_STATUSES, true) && $locked->status !== 'approved')) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'ادعای ساختاری انتخاب‌شده دیگر برای این مسیر محل سکونت قابل استفاده نیست.',
                ]);
            }
            return $locked;
        })->values();

        $policy = app(LocationStructureClaimPolicy::class);
        foreach ($claims as $claim) {
            if (! $policy->dependenciesSatisfied(
                $claim,
                array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']),
                $claims,
            )) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'پیش‌نیاز ادعای ساختاری انتخاب‌شده در همین مسیر تأیید یا انتخاب نشده است.',
                ]);
            }
        }

        return $claims;
    }

    private function cancelPendingIntentRows(User $user, string $reason, $at): void
    {
        $intents = PendingResidenceIntent::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->get();

        foreach ($intents as $intent) {
            $this->cancelIntent($intent, $reason, $at);
        }
    }

    private function cancelIntent(PendingResidenceIntent $intent, string $reason, $at): void
    {
        $metadata = $intent->metadata ?? [];
        $metadata['cancellation_reason'] = $reason;

        $intent->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => $at,
            'metadata' => $metadata,
        ])->save();
    }

    private function reconcileCanonicalGroupsIfEnabled(
        User $user,
        ?Location $location = null,
        array|Collection $structuralClaims = [],
    ): void {
        if ((bool) config('location-governance.groups_enabled', false)) {
            $this->groupMembershipReconciler->reconcile($user);
            if ($location !== null && collect($structuralClaims)->isNotEmpty()) {
                app(\App\Services\Groups\PendingLocationGroupRequestService::class)
                    ->syncForStructuralClaims($user, $location, collect($structuralClaims)->all());
            }
        }

        if ((bool) config('location-governance.runtime_enabled', false)) {
            $this->communityAreaService->reconcileMembershipsFor($user);
        }
    }
}
