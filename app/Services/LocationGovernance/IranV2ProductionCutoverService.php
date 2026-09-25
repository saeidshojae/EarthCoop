<?php

namespace App\Services\LocationGovernance;

use App\Models\LocationExternalId;
use App\Models\LocationSchema;
use App\Services\LocationGovernance\Import\ReferenceGovernanceTopologyImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class IranV2ProductionCutoverService
{
    private const OPEN_PROPOSAL_STATUSES = ['pending', 'ready_for_review', 'needs_evidence'];
    private const LIVE_CLAIM_STATUSES = ['pending', 'ready_for_review', 'needs_evidence', 'approved'];

    public function __construct(
        private readonly ReferenceGovernanceTopologyImporter $topologyImporter,
        private readonly IranV2RuntimeState $runtimeState,
    ) {
    }

    public function preflight(): array
    {
        $pairs = $this->verifiedLocationPairs();
        $allV1 = LocationExternalId::query()
            ->where('source', (string) config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
            ->where('dataset_version', (string) config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1'))
            ->pluck('location_id', 'external_id');

        $mappedV1Ids = collect($pairs)->pluck('v1_location_id')->map(fn ($id) => (int) $id)->all();
        $unmappedV1Ids = $allV1->reject(fn ($locationId) => in_array((int) $locationId, $mappedV1Ids, true))
            ->values()->map(fn ($id) => (int) $id)->all();

        $topology = $this->topologyImporter->diff('IR', 'v2');
        $referenceCount = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->whereHas('location', fn ($query) => $query->where('country_code', 'IR')->where('status', 'active'))
            ->count();

        $unmappedAreaIds = $this->officialAreaIdsForLocations($unmappedV1Ids);

        $blockers = [
            'unmapped_active_relationships' => $this->countWhereIn('user_location_relationships', 'location_id', $unmappedV1Ids, fn ($q) => $q->whereNull('ended_at')),
            'unmapped_open_proposals' => $this->countWhereIn('location_proposals', 'parent_location_id', $unmappedV1Ids, fn ($q) => $q->whereIn('status', self::OPEN_PROPOSAL_STATUSES)),
            'unmapped_live_structure_claims' => $this->countWhereIn('location_structure_claims', 'location_id', $unmappedV1Ids, fn ($q) => $q->whereIn('status', self::LIVE_CLAIM_STATUSES)),
            'unmapped_live_group_requests' => $this->countWhereIn('location_scoped_group_requests', 'location_id', $unmappedV1Ids, fn ($q) => $q->whereNotIn('status', ['cancelled', 'rejected'])),
            'unmapped_project_targets' => $this->countWhereIn('najm_bahar_projects', 'target_location_id', $unmappedV1Ids),
            'unmapped_project_scopes' => $this->countWhereIn('najm_bahar_projects', 'governance_area_id', $unmappedAreaIds),
            'unmapped_active_group_memberships' => $this->activeMembershipsForAreaIds($unmappedAreaIds),
            'unmapped_area_overrides' => $this->countWhereIn('governance_area_overrides', 'governance_area_id', $unmappedAreaIds),
            'unmapped_enabled_group_policies' => $this->countWhereIn('group_creation_policies', 'governance_area_id', $unmappedAreaIds, fn ($q) => $q->where('enabled', true)),
        ];

        $runtimeActive = $this->runtimeState->isActive();
        $topologyShapeOk = (int) ($topology['update'] ?? -1) === 0
            && (int) ($topology['conflict'] ?? -1) === 0
            && in_array((int) ($topology['create'] ?? -1), [0, 6158], true);
        $topologyComplete = (int) ($topology['create'] ?? -1) === 0
            && (int) ($topology['update'] ?? -1) === 0
            && (int) ($topology['conflict'] ?? -1) === 0
            && (int) ($topology['unchanged'] ?? 0) >= 6160;
        $blockerTotal = array_sum($blockers);

        return [
            'runtime_active' => $runtimeActive,
            'reference_v2_count' => $referenceCount,
            'verified_pairs' => count($pairs),
            'v1_identity_count' => $allV1->count(),
            'unmapped_v1_identity_count' => count($unmappedV1Ids),
            'topology' => $topology,
            'topology_shape_ok' => $topologyShapeOk,
            'topology_complete' => $topologyComplete,
            'blockers' => $blockers,
            'blocker_total' => $blockerTotal,
            'ready' => ! $runtimeActive
                && $referenceCount === 6158
                && $topologyShapeOk
                && $blockerTotal === 0,
            'complete' => $runtimeActive
                && $referenceCount === 6158
                && $topologyComplete
                && $blockerTotal === 0,
        ];
    }

    public function apply(): array
    {
        $before = $this->preflight();
        if (! $before['ready']) {
            throw new RuntimeException('Iran v2 cutover preflight is not clean.');
        }

        if ((int) $before['topology']['create'] === 6158) {
            $this->topologyImporter->apply('IR', 'v2');
        }

        $afterTopology = $this->topologyImporter->diff('IR', 'v2');
        if ((int) ($afterTopology['create'] ?? -1) !== 0
            || (int) ($afterTopology['update'] ?? -1) !== 0
            || (int) ($afterTopology['conflict'] ?? -1) !== 0
            || (int) ($afterTopology['unchanged'] ?? 0) < 6160) {
            throw new RuntimeException('Iran v2 topology did not become fully idempotent after staging.');
        }

        $pairs = $this->verifiedLocationPairs();
        $locationMap = collect($pairs)->mapWithKeys(fn (array $pair): array => [
            (int) $pair['v1_location_id'] => (int) $pair['v2_location_id'],
        ])->all();
        $areaMap = $this->verifiedAreaMap($pairs);

        $this->assertNoTargetGroupConflicts($areaMap);

        $v2Schema = LocationSchema::query()
            ->where('key', IranV2RuntimeState::SCHEMA_KEY)
            ->where('country_code', 'IR')
            ->where('version', 'v2')
            ->where('status', 'active')
            ->firstOrFail();

        $counts = DB::transaction(function () use ($locationMap, $areaMap, $v2Schema): array {
            $counts = [
                'active_relationships' => 0,
                'proposal_roots' => 0,
                'proposal_chain_rows' => 0,
                'structure_claims' => 0,
                'group_requests_locations' => 0,
                'groups' => 0,
                'elections' => 0,
                'group_requests_areas' => 0,
                'project_targets' => 0,
                'project_scopes' => 0,
                'area_overrides' => 0,
                'group_policies' => 0,
                'pending_resolved_locations' => 0,
            ];

            foreach ($locationMap as $from => $to) {
                if (Schema::hasTable('user_location_relationships')) {
                    $counts['active_relationships'] += DB::table('user_location_relationships')
                        ->where('location_id', $from)->whereNull('ended_at')->update(['location_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('location_structure_claims')) {
                    $counts['structure_claims'] += DB::table('location_structure_claims')
                        ->where('location_id', $from)->update(['location_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('location_scoped_group_requests')) {
                    $counts['group_requests_locations'] += DB::table('location_scoped_group_requests')
                        ->where('location_id', $from)->update(['location_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('najm_bahar_projects') && Schema::hasColumn('najm_bahar_projects', 'target_location_id')) {
                    $counts['project_targets'] += DB::table('najm_bahar_projects')
                        ->where('target_location_id', $from)->update(['target_location_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('pending_residence_intents') && Schema::hasColumn('pending_residence_intents', 'resolved_location_id')) {
                    $counts['pending_resolved_locations'] += DB::table('pending_residence_intents')
                        ->where('resolved_location_id', $from)->update(['resolved_location_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('location_proposals')) {
                    DB::table('location_proposals')->where('resolved_location_id', $from)
                        ->update(['resolved_location_id' => $to, 'updated_at' => now()]);
                }
            }

            $affectedProposalIds = [];
            if (Schema::hasTable('location_proposals')) {
                foreach ($locationMap as $from => $to) {
                    $roots = DB::table('location_proposals')->where('parent_location_id', $from)->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if ($roots !== []) {
                        $counts['proposal_roots'] += DB::table('location_proposals')->whereIn('id', $roots)
                            ->update(['parent_location_id' => $to, 'location_schema_id' => $v2Schema->id, 'updated_at' => now()]);
                        $affectedProposalIds = array_merge($affectedProposalIds, $roots);
                    }
                }

                $frontier = array_values(array_unique($affectedProposalIds));
                while ($frontier !== []) {
                    $children = DB::table('location_proposals')->whereIn('parent_location_proposal_id', $frontier)
                        ->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $children = array_values(array_diff(array_unique($children), $affectedProposalIds));
                    if ($children === []) {
                        break;
                    }
                    $counts['proposal_chain_rows'] += DB::table('location_proposals')->whereIn('id', $children)
                        ->update(['location_schema_id' => $v2Schema->id, 'updated_at' => now()]);
                    $affectedProposalIds = array_merge($affectedProposalIds, $children);
                    $frontier = $children;
                }
            }

            foreach ($areaMap as $from => $to) {
                if (Schema::hasTable('groups')) {
                    $counts['groups'] += DB::table('groups')->where('governance_area_id', $from)
                        ->update(['governance_area_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('elections') && Schema::hasColumn('elections', 'governance_area_id')) {
                    $counts['elections'] += DB::table('elections')->where('governance_area_id', $from)
                        ->update(['governance_area_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('location_scoped_group_requests') && Schema::hasColumn('location_scoped_group_requests', 'governance_area_id')) {
                    $counts['group_requests_areas'] += DB::table('location_scoped_group_requests')->where('governance_area_id', $from)
                        ->update(['governance_area_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('najm_bahar_projects') && Schema::hasColumn('najm_bahar_projects', 'governance_area_id')) {
                    $counts['project_scopes'] += DB::table('najm_bahar_projects')->where('governance_area_id', $from)
                        ->update(['governance_area_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('governance_area_overrides')) {
                    $counts['area_overrides'] += DB::table('governance_area_overrides')->where('governance_area_id', $from)
                        ->update(['governance_area_id' => $to, 'updated_at' => now()]);
                }
                if (Schema::hasTable('group_creation_policies')) {
                    $counts['group_policies'] += DB::table('group_creation_policies')->where('governance_area_id', $from)
                        ->update(['governance_area_id' => $to, 'updated_at' => now()]);
                }
            }

            $this->runtimeState->setActive(true);

            return $counts;
        });

        return [
            'before' => $before,
            'topology' => $afterTopology,
            'migrated' => $counts,
            'runtime_active' => $this->runtimeState->isActive(),
        ];
    }

    private function verifiedLocationPairs(): array
    {
        $source = (string) config('iran_v1_v2_crosswalk.source', 'earthcoop-reference');
        $v1Version = (string) config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1');
        $v2Version = (string) config('iran_v1_v2_crosswalk.v2_dataset_version', 'v2');
        $pairs = [];

        foreach ((array) config('iran_v1_v2_crosswalk.mappings', []) as $v1ExternalId => $mapping) {
            if (($mapping['status'] ?? null) !== 'verified_identity') {
                continue;
            }
            $v1 = LocationExternalId::query()->where('source', $source)->where('dataset_version', $v1Version)
                ->where('external_id', $v1ExternalId)->first();
            if ($v1 === null) {
                continue;
            }
            $v2 = LocationExternalId::query()->where('source', $source)->where('dataset_version', $v2Version)
                ->where('external_id', (string) ($mapping['v2'] ?? ''))->first();
            if ($v2 === null) {
                throw new RuntimeException('Verified v2 identity is missing for '.$v1ExternalId);
            }
            $pairs[] = [
                'v1_external_id' => $v1ExternalId,
                'v2_external_id' => (string) $mapping['v2'],
                'v1_location_id' => (int) $v1->location_id,
                'v2_location_id' => (int) $v2->location_id,
            ];
        }

        return $pairs;
    }

    private function verifiedAreaMap(array $pairs): array
    {
        $map = [];
        foreach ($pairs as $pair) {
            $v1Area = DB::table('governance_area_locations as pivots')
                ->join('governance_areas as areas', 'areas.id', '=', 'pivots.governance_area_id')
                ->where('pivots.location_id', $pair['v1_location_id'])
                ->where('areas.area_kind', 'official')->where('areas.status', 'active')
                ->orderByDesc('areas.rank')->value('areas.id');
            $v2Area = DB::table('governance_area_locations as pivots')
                ->join('governance_areas as areas', 'areas.id', '=', 'pivots.governance_area_id')
                ->where('pivots.location_id', $pair['v2_location_id'])
                ->where('areas.area_kind', 'official')->where('areas.status', 'active')
                ->orderByDesc('areas.rank')->value('areas.id');

            if ($v1Area !== null && $v2Area !== null) {
                $map[(int) $v1Area] = (int) $v2Area;
            }
        }
        return $map;
    }

    private function officialAreaIdsForLocations(array $locationIds): array
    {
        if ($locationIds === [] || ! Schema::hasTable('governance_area_locations')) {
            return [];
        }
        return DB::table('governance_area_locations as pivots')
            ->join('governance_areas as areas', 'areas.id', '=', 'pivots.governance_area_id')
            ->whereIn('pivots.location_id', $locationIds)
            ->where('areas.area_kind', 'official')
            ->pluck('areas.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function activeMembershipsForAreaIds(array $areaIds): int
    {
        if ($areaIds === [] || ! Schema::hasTable('groups') || ! Schema::hasTable('group_user')) {
            return 0;
        }
        return DB::table('group_user')->join('groups', 'groups.id', '=', 'group_user.group_id')
            ->where('group_user.status', 1)->whereIn('groups.governance_area_id', $areaIds)->count();
    }

    private function countWhereIn(string $table, string $column, array $ids, ?callable $scope = null): int
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }
        $query = DB::table($table)->whereIn($column, $ids);
        if ($scope !== null) {
            $scope($query);
        }
        return $query->count();
    }

    private function assertNoTargetGroupConflicts(array $areaMap): void
    {
        if ($areaMap === [] || ! Schema::hasTable('groups')) {
            return;
        }

        foreach ($areaMap as $from => $to) {
            $sourceGroups = DB::table('groups')->where('governance_area_id', $from)
                ->get(['id', 'dimension_key', 'dimension_value_key']);
            foreach ($sourceGroups as $group) {
                $conflict = DB::table('groups')
                    ->where('governance_area_id', $to)
                    ->where('dimension_key', $group->dimension_key)
                    ->where('dimension_value_key', $group->dimension_value_key)
                    ->where('id', '<>', $group->id)
                    ->exists();
                if ($conflict) {
                    throw new RuntimeException('Target v2 governance area already has a conflicting canonical group.');
                }
            }

            if (Schema::hasTable('governance_area_overrides')
                && DB::table('governance_area_overrides')->where('governance_area_id', $from)->exists()
                && DB::table('governance_area_overrides')->where('governance_area_id', $to)->exists()) {
                throw new RuntimeException('Target v2 governance area already has a conflicting capability override.');
            }

            if (Schema::hasTable('group_creation_policies')) {
                $sourcePolicies = DB::table('group_creation_policies')
                    ->where('governance_area_id', $from)
                    ->where('enabled', true)
                    ->get(['membership_dimension_id', 'priority']);
                foreach ($sourcePolicies as $policy) {
                    if (DB::table('group_creation_policies')
                        ->where('governance_area_id', $to)
                        ->where('membership_dimension_id', $policy->membership_dimension_id)
                        ->where('priority', $policy->priority)
                        ->where('enabled', true)
                        ->exists()) {
                        throw new RuntimeException('Target v2 governance area already has a conflicting group creation policy.');
                    }
                }
            }
        }
    }
}
