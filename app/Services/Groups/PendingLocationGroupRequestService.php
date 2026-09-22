<?php

namespace App\Services\Groups;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationScopedGroupRequest;
use App\Models\LocationStructureClaim;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Services\Membership\MembershipEngine;
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
            ->with('locationProposal.type')->latest('id')->first();

        return $intent?->locationProposal
            ? $this->syncForPendingResidence($user, $intent->locationProposal)
            : collect();
    }

    public function syncForPendingResidence(User $user, LocationProposal $deepest): Collection
    {
        return DB::transaction(function () use ($user, $deepest): Collection {
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
                                ],
                            ]
                        ));
                    }
                }
            }
            return $requests->values();
        });
    }

    public function syncForStructuralClaims(User $user, Location $location, array $claims): Collection
    {
        $dimensions = app(MembershipEngine::class)->dimensionValuesFor($user);
        $requests = collect();
        foreach (collect($claims)->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
            && (int) $claim->location_id === (int) $location->id
            && in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence'], true)
            && $claim->claim_type === 'no_neighborhood') as $claim) {
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
        return LocationScopedGroupRequest::query()
            ->where('requester_user_id', $user->id)->where('scope_kind', self::SCOPE)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->orderBy('id')->get();
    }

    public function presentationGroups(Collection $requests): Collection
    {
        $namer = app(GovernanceScopedGroupService::class);
        return $requests->map(function (LocationScopedGroupRequest $request) use ($namer): Group {
            $metadata = $request->metadata ?? [];
            $areaName = (string) ($metadata['canonical_name'] ?? $request->location?->canonical_name ?? 'حوزه در انتظار');
            $dimensionKey = (string) $request->dimension_key;
            $valueKey = (string) $request->dimension_value_key;
            $level = $this->presentationLevelFor((string) ($metadata['type_key'] ?? ''));
            $group = new Group([
                'name' => $namer->pendingNameFor($dimensionKey, $valueKey, $areaName),
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
                'role' => $level === 'neighborhood' ? 1 : 0,
                'status' => 1,
            ]));
            return $group;
        })->values();
    }

    public function reconcileResolvedProposal(LocationProposal $proposal, Location $location): void
    {
        $requests = LocationScopedGroupRequest::query()->where('location_proposal_id', $proposal->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])->lockForUpdate()->get();
        foreach ($requests as $request) {
            $metadata = $request->metadata ?? [];
            $metadata['resolved_from_proposal_id'] = $proposal->id;
            $request->forceFill(['location_id' => $location->id, 'location_proposal_id' => null, 'status' => 'ready_to_materialize', 'metadata' => $metadata])->save();
            $this->tryMaterializeOfficialRequest($request);
        }
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
        foreach ($requests as $request) {
            if ($claim->status === 'rejected') {
                $request->forceFill(['status' => 'rejected'])->save();
                continue;
            }
            if ($claim->status !== 'approved' || $claim->location_id === null) continue;
            $metadata = $request->metadata ?? [];
            $metadata['resolved_from_structure_claim_id'] = $claim->id;
            $request->forceFill(['location_id' => $claim->location_id, 'location_structure_claim_id' => null, 'status' => 'ready_to_materialize', 'metadata' => $metadata])->save();
            $this->tryMaterializeOfficialRequest($request);
        }
    }

    public function reconcileReadyForUser(User $user): void
    {
        LocationScopedGroupRequest::query()->where('requester_user_id', $user->id)
            ->where('status', 'ready_to_materialize')->get()
            ->each(fn (LocationScopedGroupRequest $request) => $this->tryMaterializeOfficialRequest($request));
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
        if ($group === null || ! GroupUser::query()->where('group_id', $group->id)
            ->where('user_id', $request->requester_user_id)->where('status', 1)->exists()) return;

        $request->forceFill(['governance_area_id' => $area->id, 'group_id' => $group->id, 'status' => 'materialized'])->save();
    }

    private function presentationRankFor(string $type): int
    {
        return match ($type) {
            'neighborhood' => 9,
            'urban_region', 'village' => 8,
            'city', 'rural_district' => 7,
            default => 0,
        };
    }

    private function presentationLevelFor(string $type): ?string
    {
        return match ($type) {
            'city', 'rural_district' => 'city',
            'urban_region', 'village' => 'region',
            'neighborhood' => 'neighborhood',
            default => null,
        };
    }
}
