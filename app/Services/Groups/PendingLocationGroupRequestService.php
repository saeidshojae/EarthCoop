<?php

namespace App\Services\Groups;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationScopedGroupRequest;
use App\Models\LocationStructureClaim;
use App\Models\PendingResidenceIntent;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\User;
use App\Services\Membership\MembershipEngine;
use App\Services\LocationGovernance\LocationStructureClaimPolicy;
use App\Support\LocationDisplayName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PendingLocationGroupRequestService
{
    private const OFFICIAL_TYPES = ['city', 'rural_district', 'urban_region', 'village', 'neighborhood'];
    private const OPEN_STATUSES = [LocationProposalStatus::Pending, LocationProposalStatus::ReadyForReview, LocationProposalStatus::NeedsEvidence];
    private const SCOPE = 'official_system';

    public function syncCurrentPendingResidence(User $user): Collection
    {
        $intent = PendingResidenceIntent::query()
            ->where('user_id', $user->id)->where('status', 'pending')
            ->with(['locationProposal.type', 'referenceSettlementResidenceClaim.settlement'])
            ->latest('id')->first();

        if ($intent?->locationProposal && $intent?->referenceSettlementResidenceClaim) {
            return $this->syncForReferenceSettlementProposal($user, $intent->referenceSettlementResidenceClaim, $intent->locationProposal);
        }
        if ($intent?->locationProposal) {
            return $this->syncForPendingResidence($user, $intent->locationProposal);
        }
        if ($intent?->referenceSettlementResidenceClaim) {
            return $this->syncForReferenceSettlementClaim($user, $intent->referenceSettlementResidenceClaim);
        }

        return collect();
    }

    public function syncForReferenceSettlementProposal(
        User $user,
        ReferenceSettlementResidenceClaim $claim,
        LocationProposal $proposal,
    ): Collection {
        $referenceRoot = $proposal->referenceSettlementRootProposal()?->loadMissing('type');
        $settlementRemainsBase = $referenceRoot?->type?->key !== 'neighborhood';

        return $this->syncForReferenceSettlementClaim($user, $claim, true, $settlementRemainsBase)
            ->concat($this->syncForPendingResidence($user, $proposal, true))->values();
    }

    public function syncForPendingResidence(User $user, LocationProposal $deepest, bool $preserveSettlementRequests = false): Collection
    {
        return DB::transaction(function () use ($user, $deepest, $preserveSettlementRequests): Collection {
            $pendingIntent = PendingResidenceIntent::query()
                ->where('user_id', $user->id)
                ->where('location_proposal_id', $deepest->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if (! $preserveSettlementRequests) {
                LocationScopedGroupRequest::query()
                    ->where('requester_user_id', $user->id)
                    ->whereNotNull('reference_settlement_residence_claim_id')
                    ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            $structuralClaimIds = collect(($pendingIntent?->metadata ?? [])['structural_claim_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $chain = collect();
            $cursor = $deepest->loadMissing('type');
            $visited = [];
            while ($cursor !== null && ! isset($visited[$cursor->id])) {
                $visited[$cursor->id] = true;
                $status = $cursor->status instanceof LocationProposalStatus ? $cursor->status : LocationProposalStatus::tryFrom((string) $cursor->status);
                if ($status !== null && in_array($status, self::OPEN_STATUSES, true) && in_array($cursor->type?->key, self::OFFICIAL_TYPES, true)) {
                    $chain->push($cursor);
                }
                $cursor = $cursor->parentProposal()->with('type')->first();
            }

            $activeProposalIds = $chain->pluck('id')->map(fn ($id) => (int) $id)->all();
            $stale = LocationScopedGroupRequest::query()
                ->where('requester_user_id', $user->id)->where('scope_kind', self::SCOPE)
                ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                ->whereNotNull('location_proposal_id');
            if ($activeProposalIds !== []) $stale->whereNotIn('location_proposal_id', $activeProposalIds);
            $stale->update(['status' => 'cancelled']);

            $dimensions = app(MembershipEngine::class)->dimensionValuesFor($user);
            $requests = collect();
            foreach ($chain as $proposal) {
                foreach ($dimensions as $dimensionKey => $values) {
                    foreach ($values as $valueKey) {
                        $requests->push(LocationScopedGroupRequest::query()->updateOrCreate(
                            [
                                'requester_user_id' => $user->id,
                                'location_proposal_id' => $proposal->id,
                                'scope_kind' => self::SCOPE,
                                'dimension_key' => $dimensionKey,
                                'dimension_value_key' => $valueKey,
                            ],
                            [
                                'location_id' => null, 'location_structure_claim_id' => null,
                                'status' => 'pending_location',
                                'metadata' => [
                                    'type_key' => $proposal->type?->key,
                                    'canonical_name' => $proposal->canonical_name,
                                    'source' => 'pending_primary_residence',
                                    'is_pending_base' => (int) $proposal->id === (int) $deepest->id,
                                    'structural_claim_ids' => $structuralClaimIds,
                                ],
                            ]
                        ));
                    }
                }
            }
            return $requests->values();
        });
    }

    public function syncForReferenceSettlementClaim(
        User $user,
        ReferenceSettlementResidenceClaim $claim,
        bool $preserveProposalRequests = false,
        bool $isPendingBase = true,
    ): Collection {
        return DB::transaction(function () use ($user, $claim, $preserveProposalRequests, $isPendingBase): Collection {
            $lockedClaim = ReferenceSettlementResidenceClaim::query()
                ->with('settlement')
                ->whereKey($claim->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedClaim->status, ['pending', 'needs_evidence', 'residential_evidence_verified'], true)
                || ! $lockedClaim->settlement instanceof ReferenceSettlement) {
                LocationScopedGroupRequest::query()
                    ->where('requester_user_id', $user->id)
                    ->where('reference_settlement_residence_claim_id', $lockedClaim->id)
                    ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
                return collect();
            }

            LocationScopedGroupRequest::query()
                ->where('requester_user_id', $user->id)
                ->whereNotNull('reference_settlement_residence_claim_id')
                ->where('reference_settlement_residence_claim_id', '<>', $lockedClaim->id)
                ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            if (! $preserveProposalRequests) {
                LocationScopedGroupRequest::query()
                    ->where('requester_user_id', $user->id)
                    ->whereNotNull('location_proposal_id')
                    ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }

            $settlement = $lockedClaim->settlement;
            $dimensions = app(MembershipEngine::class)->dimensionValuesFor($user);
            $requests = collect();
            $displayName = trim((string) $settlement->name_fa);
            if ($displayName !== '' && ! str_starts_with($displayName, 'آبادی ') && ! str_starts_with($displayName, 'روستای ')) {
                $displayName = 'آبادی '.$displayName;
            }

            foreach ($dimensions as $dimensionKey => $values) {
                foreach ($values as $valueKey) {
                    $requests->push(LocationScopedGroupRequest::query()->updateOrCreate(
                        [
                            'requester_user_id' => $user->id,
                            'reference_settlement_residence_claim_id' => $lockedClaim->id,
                            'scope_kind' => self::SCOPE,
                            'dimension_key' => $dimensionKey,
                            'dimension_value_key' => $valueKey,
                        ],
                        [
                            'location_id' => null,
                            'location_proposal_id' => null,
                            'location_structure_claim_id' => null,
                            'status' => 'pending_location',
                            'group_id' => null,
                            'governance_area_id' => null,
                            'metadata' => [
                                'type_key' => $settlement->classification === 'verified_residential_village' ? 'village' : 'settlement',
                                'canonical_name' => $displayName !== '' ? $displayName : $settlement->external_id,
                                'source' => 'pending_reference_settlement_residence',
                                'is_pending_base' => $isPendingBase,
                                'reference_settlement_external_id' => $settlement->external_id,
                                'reference_settlement_parent_external_id' => $settlement->parent_external_id,
                                'residential_eligibility' => $settlement->residential_eligibility,
                            ],
                        ]
                    ));
                }
            }

            return $requests->values();
        });
    }

    public function syncCurrentStructuralClaims(User $user): Collection
    {
        $relationship = $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->latest('id')
            ->first();

        if ($relationship === null) {
            return collect();
        }

        $claimIds = collect(($relationship->metadata ?? [])['structural_claim_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($claimIds->isEmpty()) {
            $this->cancelStaleStructuralRequests($user, []);
            return collect();
        }

        $claims = LocationStructureClaim::query()
            ->whereIn('id', $claimIds)
            ->where('location_id', $relationship->location_id)
            ->where('claim_type', 'no_neighborhood')
            ->whereIn('status', ['pending', 'ready_for_review', 'needs_evidence'])
            ->get();

        return $this->syncForStructuralClaims(
            $user,
            Location::query()->findOrFail($relationship->location_id),
            $claims->all(),
        );
    }

    public function syncForStructuralClaims(User $user, Location $location, array $claims): Collection
    {
        $policy = app(LocationStructureClaimPolicy::class);
        $activeClaims = collect($claims)->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
            && (int) $claim->location_id === (int) $location->id
            && in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence'], true)
            && $claim->claim_type === 'no_neighborhood'
            && $policy->dependenciesSatisfied(
                $claim,
                array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']),
                collect($claims),
            ))->values();

        $this->cancelStaleStructuralRequests($user, $activeClaims->pluck('id')->map(fn ($id) => (int) $id)->all());

        $dimensions = app(MembershipEngine::class)->dimensionValuesFor($user);
        $requests = collect();
        foreach ($activeClaims as $claim) {
            foreach ($dimensions as $dimensionKey => $values) {
                foreach ($values as $valueKey) {
                    $requests->push(LocationScopedGroupRequest::query()->updateOrCreate(
                        [
                            'requester_user_id' => $user->id,
                            'location_structure_claim_id' => $claim->id,
                            'scope_kind' => self::SCOPE,
                            'dimension_key' => $dimensionKey,
                            'dimension_value_key' => $valueKey,
                        ],
                        [
                            'location_id' => null, 'location_proposal_id' => null,
                            'status' => 'pending_location',
                            'metadata' => [
                                'claim_type' => $claim->claim_type,
                                'type_key' => $location->type?->key,
                                'canonical_name' => $location->canonical_name,
                                'source' => 'pending_primary_residence_structure',
                                'is_pending_base' => true,
                            ],
                        ]
                    ));
                }
            }
        }
        return $requests->values();
    }

    public function openForUser(User $user): Collection
    {
        $this->syncCurrentPendingResidence($user);
        $this->syncCurrentStructuralClaims($user);

        return LocationScopedGroupRequest::query()
            ->where('requester_user_id', $user->id)->where('scope_kind', self::SCOPE)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->with(['locationProposal', 'locationStructureClaim.location', 'referenceSettlementResidenceClaim.settlement', 'location'])
            ->orderBy('id')->get();
    }

    /**
     * Present a chosen pending no-neighborhood base once. Its canonical official
     * group remains materialized with its observer pivot for audit/approval, but
     * the pending shell represents that same area and dimension in user-facing
     * group counts. Do not suppress unrelated upstream observers or other groups.
     *
     * @param Collection<int, Group> $canonicalGroups
     * @param Collection<int, LocationScopedGroupRequest> $pendingRequests
     * @return Collection<int, Group>
     */
    public function presentableCanonicalGroups(Collection $canonicalGroups, Collection $pendingRequests): Collection
    {
        if ($canonicalGroups->isEmpty() || $pendingRequests->isEmpty()) {
            return $canonicalGroups->values();
        }

        $structuralRequests = $pendingRequests
            ->filter(fn (LocationScopedGroupRequest $request): bool =>
                $request->location_structure_claim_id !== null
                && (bool) data_get($request->metadata, 'is_pending_base', false))
            ->values();
        if ($structuralRequests->isEmpty()) {
            return $canonicalGroups->values();
        }

        $claims = LocationStructureClaim::query()
            ->whereIn('id', $structuralRequests->pluck('location_structure_claim_id')->unique())
            ->where('claim_type', 'no_neighborhood')
            ->whereIn('status', ['pending', 'ready_for_review', 'needs_evidence'])
            ->whereNotNull('location_id')
            ->get(['id', 'location_id'])
            ->keyBy('id');
        if ($claims->isEmpty()) {
            return $canonicalGroups->values();
        }

        $areas = GovernanceArea::query()
            ->official()->active()
            ->whereIn('id', $canonicalGroups->pluck('governance_area_id')->filter()->unique())
            ->whereHas('locations', fn ($query) => $query->whereIn('locations.id', $claims->pluck('location_id')->unique()))
            ->with(['locations' => fn ($query) => $query->whereIn('locations.id', $claims->pluck('location_id')->unique())])
            ->get();

        $suppressed = [];
        foreach ($structuralRequests as $request) {
            $locationId = $claims->get($request->location_structure_claim_id)?->location_id;
            if ($locationId === null) {
                continue;
            }
            foreach ($areas as $area) {
                if ($area->locations->contains('id', (int) $locationId)) {
                    $key = $request->dimension_key.'|'.$request->dimension_value_key;
                    $suppressed[(int) $area->id][$key] = true;
                }
            }
        }

        return $canonicalGroups->reject(function (Group $group) use ($suppressed): bool {
            if ((int) ($group->pivot?->role ?? -1) !== 0) {
                return false;
            }
            $key = $group->dimension_key.'|'.$group->dimension_value_key;
            return isset($suppressed[(int) $group->governance_area_id][$key]);
        })->values();
    }

    public function presentationGroups(Collection $requests): Collection
    {
        $namer = app(GovernanceScopedGroupService::class);
        return $requests->map(function (LocationScopedGroupRequest $request) use ($namer): Group {
            $metadata = $request->metadata ?? [];
            // Resolve the current model name rather than the canonical-name snapshot.
            // Existing pending requests may predate localized metadata; no rewrite
            // or migration of those requests is required for Persian presentation.
            $area = $request->locationStructureClaim?->location
                ?? $request->locationProposal
                ?? $request->location
                ?? $request->referenceSettlementResidenceClaim?->settlement;
            $areaName = $area instanceof Location || $area instanceof LocationProposal
                ? LocationDisplayName::for($area)
                : ($area instanceof ReferenceSettlement
                    ? (string) ($metadata['canonical_name'] ?? $area->name_fa)
                    : (string) ($metadata['canonical_name'] ?? 'حوزه در انتظار'));
            $dimensionKey = (string) $request->dimension_key;
            $valueKey = (string) $request->dimension_value_key;
            $level = $this->presentationLevelFor((string) ($metadata['type_key'] ?? ''));
            $group = new Group([
                'name' => $namer->pendingNameFor(
                    $dimensionKey,
                    $valueKey,
                    $areaName,
                    (string) ($metadata['type_key'] ?? ''),
                ),
                'group_type' => match ($dimensionKey) {
                    'public' => '0', 'profession' => '1', 'specialty' => '2',
                    'age' => '3', 'gender' => '4', default => '0',
                },
                'location_level' => $level,
                'dimension_key' => $dimensionKey,
                'dimension_value_key' => $valueKey,
            ]);
            $group->setAttribute('pending_location_request_id', $request->id);
            $group->setAttribute('pending_location', true);
            $group->setAttribute('presentation_rank', $this->presentationRankFor((string) ($metadata['type_key'] ?? '')));
            $group->setRelation('pivot', new GroupUser([
                'user_id' => $request->requester_user_id,
                'role' => (bool) ($metadata['is_pending_base'] ?? false) ? 1 : 0,
                'status' => 1,
            ]));
            return $group;
        })->values();
    }

    public function ensureApprovedOfficialTopology(LocationProposal $proposal, Location $location): ?GovernanceArea
    {
        $typeKey = (string) $proposal->type?->key;
        if (! in_array($typeKey, self::OFFICIAL_TYPES, true)) {
            return null;
        }

        $existing = $location->governanceAreas()->official()->active()
            ->orderByDesc('rank')->orderBy('governance_areas.id')->first();
        if ($existing !== null) {
            return $existing;
        }

        $parentLocation = $location->parent()->first();
        $parentArea = $parentLocation?->governanceAreas()->official()->active()
            ->orderByDesc('rank')->orderBy('governance_areas.id')->first();

        if ($parentArea === null) {
            return null;
        }

        $governanceType = $typeKey === 'neighborhood' ? 'local' : $typeKey;
        $rank = match ($typeKey) {
            'city', 'rural_district' => 500,
            'urban_region', 'village' => 700,
            'neighborhood' => 900,
            default => 0,
        };

        $area = GovernanceArea::query()->firstOrCreate(
            ['key' => 'approved-location-'.$location->id],
            [
                'parent_id' => $parentArea->id,
                'country_code' => $location->country_code,
                'governance_type' => $governanceType,
                'area_kind' => 'official',
                'canonical_name' => $location->canonical_name,
                'localized_names' => $location->localized_names,
                'rank' => $rank,
                'status' => 'active',
                'metadata' => [
                    'source' => 'approved_location_proposal',
                    'location_proposal_id' => $proposal->id,
                ],
            ],
        );

        $area->locations()->syncWithoutDetaching([$location->id]);

        return $area;
    }

    public function reconcileResolvedProposal(LocationProposal $proposal, Location $location): void
    {
        $requests = LocationScopedGroupRequest::query()->where('location_proposal_id', $proposal->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])->lockForUpdate()->get();
        foreach ($requests as $request) {
            $metadata = $request->metadata ?? [];
            $metadata['resolved_from_proposal_id'] = $proposal->id;

            $dependencyIds = collect($metadata['structural_claim_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $rejectedDependency = $dependencyIds->isNotEmpty()
                ? LocationStructureClaim::query()
                    ->whereIn('id', $dependencyIds)
                    ->where('status', 'rejected')
                    ->orderBy('id')
                    ->first()
                : null;

            if ((bool) ($metadata['is_pending_base'] ?? false) && $rejectedDependency !== null) {
                $request->forceFill([
                    'location_id' => null,
                    'location_proposal_id' => null,
                    'location_structure_claim_id' => $rejectedDependency->id,
                    'status' => 'rejected',
                    'metadata' => $metadata,
                ])->save();
                continue;
            }

            $blockingClaim = $dependencyIds->isNotEmpty()
                ? LocationStructureClaim::query()
                    ->whereIn('id', $dependencyIds)
                    ->where('location_id', $location->id)
                    ->where('claim_type', 'no_neighborhood')
                    ->whereIn('status', ['pending', 'ready_for_review', 'needs_evidence'])
                    ->orderBy('id')
                    ->first()
                : null;

            if ((bool) ($metadata['is_pending_base'] ?? false) && $blockingClaim !== null) {
                $request->forceFill([
                    'location_id' => null,
                    'location_proposal_id' => null,
                    'location_structure_claim_id' => $blockingClaim->id,
                    'status' => 'pending_location',
                    'metadata' => $metadata,
                ])->save();
                continue;
            }

            $request->forceFill([
                'location_id' => $location->id,
                'location_proposal_id' => null,
                'location_structure_claim_id' => null,
                'status' => 'ready_to_materialize',
                'metadata' => $metadata,
            ])->save();
            $this->tryMaterializeOfficialRequest($request);
        }
    }

    public function rejectForReferenceSettlementClaim(ReferenceSettlementResidenceClaim $claim): void
    {
        LocationScopedGroupRequest::query()
            ->where('reference_settlement_residence_claim_id', $claim->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->update(['status' => 'rejected', 'updated_at' => now()]);
    }

    public function rejectForProposal(LocationProposal $proposal): void
    {
        LocationScopedGroupRequest::query()->where('location_proposal_id', $proposal->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->update(['status' => 'rejected', 'updated_at' => now()]);
    }

    public function reconcileStructuralClaim(LocationStructureClaim $claim): void
    {
        $requests = LocationScopedGroupRequest::query()->where('location_structure_claim_id', $claim->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])->lockForUpdate()->get();
        if ($claim->status === 'rejected') {
            LocationScopedGroupRequest::query()
                ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                ->whereJsonContains('metadata->structural_claim_ids', (int) $claim->id)
                ->update(['status' => 'rejected', 'updated_at' => now()]);

            $dependentProposalIds = LocationProposal::query()
                ->whereIn('status', self::OPEN_STATUSES)
                ->whereJsonContains('metadata->structural_claim_ids', (int) $claim->id)
                ->pluck('id');

            if ($dependentProposalIds->isNotEmpty()) {
                LocationScopedGroupRequest::query()
                    ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                    ->whereIn('location_proposal_id', $dependentProposalIds)
                    ->update(['status' => 'rejected', 'updated_at' => now()]);
            }
        }

        foreach ($requests as $request) {
            if ($claim->status === 'rejected') {
                $request->forceFill(['status' => 'rejected'])->save();
                continue;
            }
            if ($claim->status !== 'approved' || $claim->location_id === null) continue;
            $metadata = $request->metadata ?? [];
            $metadata['resolved_from_structure_claim_id'] = $claim->id;
            $request->forceFill([
                'location_id' => $claim->location_id,
                'location_structure_claim_id' => null,
                'status' => 'ready_to_materialize',
                'metadata' => $metadata,
            ])->save();
            $this->healApprovedOfficialTopologyForRequest($request);
            $this->tryMaterializeOfficialRequest($request);
        }
    }

    public function reconcileReadyForUser(User $user): void
    {
        LocationScopedGroupRequest::query()->where('requester_user_id', $user->id)
            ->where('status', 'ready_to_materialize')->get()
            ->each(function (LocationScopedGroupRequest $request): void {
                $this->healApprovedOfficialTopologyForRequest($request);
                $this->tryMaterializeOfficialRequest($request);
            });
    }

    private function healApprovedOfficialTopologyForRequest(LocationScopedGroupRequest $request): void
    {
        if ($request->scope_kind !== self::SCOPE || $request->location_id === null) {
            return;
        }

        $location = Location::query()->find($request->location_id);
        if ($location === null || $location->governanceAreas()->official()->active()->exists()) {
            return;
        }

        $resolvedProposalId = (int) (($request->metadata ?? [])['resolved_from_proposal_id'] ?? 0);
        if ($resolvedProposalId <= 0) {
            return;
        }

        $proposal = LocationProposal::query()
            ->with('type')
            ->whereKey($resolvedProposalId)
            ->where('resolved_location_id', $location->id)
            ->whereIn('status', [
                LocationProposalStatus::Approved->value,
                LocationProposalStatus::Merged->value,
            ])
            ->first();

        if ($proposal === null) {
            return;
        }

        $this->ensureApprovedOfficialTopology($proposal, $location);
    }

    private function tryMaterializeOfficialRequest(LocationScopedGroupRequest $request): void
    {
        if ($request->scope_kind !== self::SCOPE || $request->location_id === null || !$request->dimension_key || !$request->dimension_value_key) return;
        $area = Location::query()->find($request->location_id)?->governanceAreas()->official()->active()
            ->orderByDesc('rank')->orderBy('governance_areas.id')->first();
        if ($area === null) return;

        app(CanonicalGroupMembershipReconciler::class)->reconcile(User::query()->findOrFail($request->requester_user_id));
        $group = Group::query()->where('governance_area_id', $area->id)
            ->where('dimension_key', $request->dimension_key)
            ->where('dimension_value_key', $request->dimension_value_key)->first();
        if ($group === null) return;

        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->requester_user_id)
            ->where('status', 1);

        if ((bool) (($request->metadata ?? [])['is_pending_base'] ?? false)) {
            $membership->where('role', '<>', 0);
        }

        if (! $membership->exists()) return;

        $request->forceFill(['governance_area_id' => $area->id, 'group_id' => $group->id, 'status' => 'materialized'])->save();
    }

    private function cancelStaleStructuralRequests(User $user, array $activeClaimIds): void
    {
        $stale = LocationScopedGroupRequest::query()
            ->where('requester_user_id', $user->id)
            ->where('scope_kind', self::SCOPE)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->whereNotNull('location_structure_claim_id');

        if ($activeClaimIds !== []) {
            $stale->whereNotIn('location_structure_claim_id', $activeClaimIds);
        }

        $stale->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    private function presentationRankFor(string $type): int
    {
        return match ($type) {
            'neighborhood' => 9,
            'urban_region', 'village', 'settlement' => 8,
            'city', 'rural_district' => 7,
            default => 0,
        };
    }

    private function presentationLevelFor(string $type): ?string
    {
        return match ($type) {
            'city', 'rural_district' => 'city',
            'urban_region', 'village', 'settlement' => 'region',
            'neighborhood' => 'neighborhood',
            default => null,
        };
    }
}
