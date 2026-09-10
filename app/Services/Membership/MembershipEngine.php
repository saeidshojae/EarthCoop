<?php

namespace App\Services\Membership;

use App\Data\Membership\MembershipIntent;
use App\Data\Membership\MembershipResolution;
use App\Models\GroupCreationPolicy;
use App\Models\MembershipDimension;
use App\Models\User;
use App\Services\LocationGovernance\GovernanceResolver;
use Illuminate\Support\Collection;

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

    public function resolve(User $user, bool $materialize = false): MembershipResolution
    {
        $residence = $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->with('location')
            ->latest('started_at')
            ->first()?->location;

        $officialAreas = $residence === null
            ? collect()
            : $this->governanceResolver->officialAreasForResidence($residence)
                ->sortBy(fn ($area) => sprintf('%010d:%s', (int) $area->rank, (string) $area->key))
                ->values();

        // Community topology is intentionally separate and is introduced in C10.
        $communityAreas = collect();
        $materializable = collect();
        $suppressed = collect();

        $dimensions = MembershipDimension::query()
            ->where('status', 'active')
            ->whereIn('key', array_keys($this->resolvers))
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        foreach ($this->resolvers as $dimensionKey => $resolver) {
            $dimension = $dimensions->get($dimensionKey);
            if ($dimension === null) {
                continue;
            }

            $values = $resolver->valuesFor($user)->map(fn ($value) => (string) $value)->sort()->values();
            foreach ($officialAreas as $area) {
                $policy = $this->policyFor($dimension->id, $area->id);
                foreach ($values as $valueKey) {
                    $mode = $policy?->mode?->value ?? 'disabled';
                    $reason = match ($mode) {
                        'automatic' => null,
                        'threshold' => 'threshold',
                        'on_demand' => 'on_demand',
                        'disabled' => 'disabled',
                        default => 'disabled',
                    };

                    $intent = new MembershipIntent(
                        dimensionKey: $dimensionKey,
                        valueKey: $valueKey,
                        governanceAreaId: (int) $area->id,
                        mode: $mode,
                        threshold: $policy?->threshold,
                        policyVersion: $policy?->policy_version,
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
            'official_governance_areas' => $officialAreas->map(fn ($area) => ['id' => (int) $area->id, 'key' => (string) $area->key])->values()->all(),
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
            // C6 records the deterministic decision only; physical Group creation begins in C7.
            $this->auditService->record($user, $resolution);
        }

        return $resolution;
    }

    private function policyFor(int $dimensionId, int $governanceAreaId): ?GroupCreationPolicy
    {
        return GroupCreationPolicy::query()
            ->where('membership_dimension_id', $dimensionId)
            ->where(function ($query) use ($governanceAreaId): void {
                $query->where('governance_area_id', $governanceAreaId)
                    ->orWhereNull('governance_area_id');
            })
            ->orderByRaw('governance_area_id IS NULL')
            ->orderByDesc('id')
            ->first();
    }
}
