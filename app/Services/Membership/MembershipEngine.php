<?php

namespace App\Services\Membership;

use App\Data\Membership\MembershipIntent;
use App\Data\Membership\MembershipResolution;
use App\Models\GroupCreationPolicy;
use App\Models\MembershipDimension;
use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\LocationGovernance\GovernanceResolver;

class MembershipEngine
{
    /** @var array<string, object> */
    private array $resolvers;

    public function __construct(
        private readonly GovernanceResolver $governanceResolver,
        PublicDimensionResolver $publicDimensionResolver,
        ProfessionDimensionResolver $professionDimensionResolver,
        SpecialtyDimensionResolver $specialtyDimensionResolver,
        AgeDimensionResolver $ageDimensionResolver,
        GenderDimensionResolver $genderDimensionResolver,
        private readonly MembershipAuditService $auditService,
    ) {
        $this->resolvers = collect([
            $publicDimensionResolver,
            $professionDimensionResolver,
            $specialtyDimensionResolver,
            $ageDimensionResolver,
            $genderDimensionResolver,
        ])->keyBy(fn ($resolver) => $resolver->dimensionKey())->all();
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, string>> */
    public function dimensionValuesFor(User $user): \Illuminate\Support\Collection
    {
        $enabled = MembershipDimension::query()
            ->where('enabled', true)
            ->whereIn('key', array_keys($this->resolvers))
            ->pluck('key')
            ->all();

        return collect($this->resolvers)
            ->filter(fn ($resolver, string $key): bool => in_array($key, $enabled, true))
            ->map(fn ($resolver) => $resolver->valuesFor($user)
                ->map(fn ($value) => (string) $value)
                ->unique()->sort()->values());
    }

    public function resolve(User $user, bool $materialize = false): MembershipResolution
    {
        $relationship = $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->with('location')
            ->latest('started_at')
            ->latest('id')
            ->first();
        $residence = $relationship?->location;

        $officialAreas = $residence === null
            ? collect()
            : $this->governanceResolver->officialAreasForResidence($residence)
                ->sortBy(fn ($area) => sprintf('%010d:%s', (int) $area->rank, (string) $area->key))
                ->values();

        // A chosen but unapproved no-neighborhood base is represented by a
        // pending group request. Do not ALSO materialize its canonical official
        // group as an observer: that double-counts the same village/region in
        // Home, My Groups and Location/Governance. Retain all upstream areas.
        $selectedClaimIds = collect(($relationship?->metadata ?? [])['structural_claim_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        if ($residence !== null && $selectedClaimIds->isNotEmpty()
            && LocationStructureClaim::query()
                ->whereIn('id', $selectedClaimIds)
                ->where('location_id', $residence->id)
                ->where('claim_type', 'no_neighborhood')
                ->whereIn('status', ['pending', 'ready_for_review', 'needs_evidence'])
                ->exists()) {
            $officialAreas = $officialAreas
                ->reject(fn ($area): bool => $area->locations()->whereKey($residence->id)->exists())
                ->values();
        }

        $communityAreas = collect();
        $materializable = collect();
        $suppressed = collect();

        $dimensions = MembershipDimension::query()
            ->where('enabled', true)
            ->whereIn('key', array_keys($this->resolvers))
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        foreach ($this->dimensionValuesFor($user) as $dimensionKey => $values) {
            $dimension = $dimensions->get($dimensionKey);
            if ($dimension === null) {
                continue;
            }

            foreach ($officialAreas as $area) {
                $policy = $this->policyFor($dimension->id, $area->id);
                foreach ($values as $valueKey) {
                    $mode = $policy?->mode?->value ?? 'disabled';
                    $reason = match ($mode) {
                        'automatic' => null,
                        'threshold' => 'threshold',
                        'on_demand' => 'on_demand',
                        default => 'disabled',
                    };

                    $intent = new MembershipIntent(
                        dimensionKey: $dimensionKey,
                        valueKey: $valueKey,
                        governanceAreaId: (int) $area->id,
                        mode: $mode,
                        threshold: $policy?->threshold,
                        policyVersion: $policy?->metadata['policy_version'] ?? null,
                        suppressionReason: $reason,
                    );

                    ($reason === null ? $materializable : $suppressed)->push($intent);
                }
            }
        }

        $sort = fn (MembershipIntent $intent) => sprintf(
            '%010d|%s|%s|%s',
            $intent->governanceAreaId ?? 0,
            $intent->dimensionKey,
            $intent->valueKey,
            $intent->mode,
        );
        $materializable = $materializable->sortBy($sort)->values();
        $suppressed = $suppressed->sortBy($sort)->values();

        $canonical = [
            'official_governance_areas' => $officialAreas
                ->map(fn ($area) => ['id' => (int) $area->id, 'key' => (string) $area->key])
                ->values()
                ->all(),
            'community_areas' => [],
            'materializable_intents' => $materializable->map->canonical()->all(),
            'suppressed_intents' => $suppressed->map->canonical()->all(),
        ];
        $fingerprint = hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $resolution = new MembershipResolution(
            officialGovernanceAreas: collect($canonical['official_governance_areas']),
            communityAreas: $communityAreas,
            materializableIntents: $materializable,
            suppressedIntents: $suppressed,
            auditFingerprint: $fingerprint,
        );

        if ($materialize) {
            $this->auditService->record($user, $resolution);
        }

        return $resolution;
    }

    private function policyFor(int $dimensionId, int $governanceAreaId): ?GroupCreationPolicy
    {
        return GroupCreationPolicy::query()
            ->where('membership_dimension_id', $dimensionId)
            ->where('enabled', true)
            ->where(function ($query) use ($governanceAreaId): void {
                $query->where('governance_area_id', $governanceAreaId)
                    ->orWhereNull('governance_area_id');
            })
            ->orderByRaw('governance_area_id IS NULL')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();
    }
}
