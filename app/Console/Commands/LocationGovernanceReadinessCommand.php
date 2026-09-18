<?php

namespace App\Console\Commands;

use App\Models\GovernanceCapabilityPolicy;
use App\Models\GroupCreationPolicy;
use App\Models\MembershipDimension;
use App\Services\LocationGovernance\Import\ReferenceGovernanceTopologyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LocationGovernanceReadinessCommand extends Command
{
    protected $signature = 'location-governance:readiness {--json : Emit the readiness report as JSON}';

    protected $description = 'Perform read-only, fail-closed preflight checks for the Location/Governance production cutover.';

    /**
     * These migrations are additive prerequisites for the canonical runtime.
     * The command never runs or rolls back migrations; it only verifies them.
     */
    private const REQUIRED_MIGRATIONS = [
        '2026_09_10_000001_create_location_core_tables',
        '2026_09_10_000002_create_governance_core_tables',
        '2026_09_10_000003_create_user_location_relationships',
        '2026_09_10_000004_create_location_import_audit_tables',
        '2026_09_10_000005_create_membership_dimension_tables',
        '2026_09_10_000006_create_membership_resolution_audits',
        '2026_09_10_000007_add_canonical_scope_to_groups',
        '2026_09_10_000008_add_governance_scope_to_elections',
        '2026_09_10_000009_add_governance_scope_to_spatial_consumers',
        '2026_09_10_000011_create_location_proposal_tables',
        '2026_09_13_000001_create_pending_residence_intents_table',
        '2026_09_14_000001_add_target_location_to_najm_bahar_projects',
        '2026_09_16_200000_enable_location_proposal_chains',
        '2026_09_18_000001_allow_direct_buildings_under_streets_and_alleys',
    ];

    private const STAGE_C_DIMENSIONS = [
        'public',
        'profession',
        'specialty',
        'age',
        'gender',
    ];

    public function handle(): int
    {
        $checks = [];

        $this->check($checks, 'required migrations', $this->requiredMigrationsReady(), 'one or more required Location/Governance migrations are missing');
        $this->check($checks, 'target schema', $this->targetSchemaReady(), 'target location schema is missing or inactive');

        $import = $this->latestTargetImport();
        $this->check($checks, 'reference import', $import !== null, 'target reference import evidence is missing');
        $this->check($checks, 'reference import status', $import !== null && $import->status === 'completed', 'target reference import is not completed');
        $this->check($checks, 'reference import conflicts', $import !== null && (int) $import->conflicts === 0 && $this->importConflictRows($import->id) === 0, 'reference import conflicts remain unresolved');
        $this->check($checks, 'reference traversal', $this->referenceTraversalReady(), 'target reference root is missing, inactive, or has no active schema-valid child');

        $this->check($checks, 'governance mappings', $this->hasGovernanceMappings(), 'canonical governance mappings are missing');
        $this->check($checks, 'governance topology', $this->governanceTopologyReady(), 'reviewed reference governance topology is missing, conflicting, or not idempotent');
        $this->check($checks, 'Stage C group policies', $this->stageCGroupPoliciesReady(), 'canonical automatic group policies are not fully activated for Stage C');
        $this->check($checks, 'rollout flags', $this->rolloutFlagsAreBoolean(), 'one or more rollout flags are invalid');
        $this->check($checks, 'proposal fatal conflicts', ! $this->hasFatalProposalConflicts(), 'fatal location proposal conflicts remain unresolved');

        $validationSha = trim((string) config('location-governance.validation_sha', ''));
        $this->check($checks, 'validation SHA', (bool) preg_match('/^[0-9a-f]{40}$/i', $validationSha), 'validated release SHA evidence is missing or invalid');

        $uatEvidence = trim((string) config('location-governance.uat_evidence', ''));
        $this->check($checks, 'UAT evidence', $uatEvidence !== '', 'UAT/Full Validation evidence is missing');

        $ready = collect($checks)->every(fn (array $check): bool => $check['passed']);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ready' => $ready,
                'target' => [
                    'country' => config('location-governance.target_country'),
                    'schema' => config('location-governance.target_schema'),
                    'dataset_source' => config('location-governance.target_dataset_source'),
                    'dataset_version' => config('location-governance.target_dataset_version'),
                ],
                'validation_sha' => $validationSha ?: null,
                'uat_evidence' => $uatEvidence ?: null,
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $check) {
                $prefix = $check['passed'] ? 'PASS' : 'FAIL';
                $message = sprintf('[%s] %s', $prefix, $check['name']);
                if (! $check['passed']) {
                    $message .= ': '.$check['failure'];
                }
                $check['passed'] ? $this->info($message) : $this->error($message);
            }

            $ready ? $this->info('READY') : $this->error('NOT READY');
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    private function requiredMigrationsReady(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        try {
            $applied = DB::table('migrations')->whereIn('migration', self::REQUIRED_MIGRATIONS)->pluck('migration')->all();
            return count(array_diff(self::REQUIRED_MIGRATIONS, $applied)) === 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function targetSchemaReady(): bool
    {
        if (! Schema::hasTable('location_schemas')) {
            return false;
        }

        try {
            return DB::table('location_schemas')
                ->where('key', (string) config('location-governance.target_schema'))
                ->where('country_code', (string) config('location-governance.target_country'))
                ->where('status', 'active')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function latestTargetImport(): ?object
    {
        if (! Schema::hasTable('location_import_runs')) {
            return null;
        }

        try {
            return DB::table('location_import_runs')
                ->where('country_code', (string) config('location-governance.target_country'))
                ->where('source', (string) config('location-governance.target_dataset_source'))
                ->where('dataset_version', (string) config('location-governance.target_dataset_version'))
                ->where('mode', 'apply')
                ->latest('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function importConflictRows(int $runId): int
    {
        if (! Schema::hasTable('location_import_conflicts')) {
            return 0;
        }

        try {
            return DB::table('location_import_conflicts')->where('location_import_run_id', $runId)->count();
        } catch (Throwable) {
            return PHP_INT_MAX;
        }
    }

    private function referenceTraversalReady(): bool
    {
        foreach (['location_external_ids', 'locations', 'location_schemas', 'location_schema_types', 'location_type_relations'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        try {
            $root = DB::table('location_external_ids as external_ids')
                ->join('locations as roots', 'roots.id', '=', 'external_ids.location_id')
                ->join('location_schemas as schemas', 'schemas.id', '=', 'roots.location_schema_id')
                ->join('location_schema_types as schema_types', function ($join): void {
                    $join->on('schema_types.location_schema_id', '=', 'roots.location_schema_id')->on('schema_types.location_type_id', '=', 'roots.location_type_id');
                })
                ->where('external_ids.source', (string) config('location-governance.target_dataset_source'))
                ->where('external_ids.dataset_version', (string) config('location-governance.target_dataset_version'))
                ->where('schemas.key', (string) config('location-governance.target_schema'))
                ->where('schemas.country_code', (string) config('location-governance.target_country'))
                ->where('schemas.status', 'active')
                ->where('roots.country_code', (string) config('location-governance.target_country'))
                ->where('roots.status', 'active')
                ->whereNull('roots.parent_id')
                ->where('schema_types.is_root', true)
                ->select(['roots.id', 'roots.location_schema_id', 'roots.location_type_id'])->first();

            if ($root === null) {
                return false;
            }

            return DB::table('locations as children')
                ->join('location_type_relations as relations', function ($join): void {
                    $join->on('relations.location_schema_id', '=', 'children.location_schema_id')->on('relations.child_type_id', '=', 'children.location_type_id');
                })
                ->where('children.parent_id', $root->id)
                ->where('children.location_schema_id', $root->location_schema_id)
                ->where('children.country_code', (string) config('location-governance.target_country'))
                ->where('children.status', 'active')
                ->where('relations.parent_type_id', $root->location_type_id)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function hasGovernanceMappings(): bool
    {
        if (! Schema::hasTable('governance_area_locations')) {
            return false;
        }
        try {
            return DB::table('governance_area_locations')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function governanceTopologyReady(): bool
    {
        if (! Schema::hasTable('governance_areas') || ! Schema::hasTable('governance_area_locations')) {
            return false;
        }
        try {
            $counts = app(ReferenceGovernanceTopologyImporter::class)->diff((string) config('location-governance.target_country'), (string) config('location-governance.target_dataset_version'));
            return (int) ($counts['create'] ?? PHP_INT_MAX) === 0
                && (int) ($counts['update'] ?? PHP_INT_MAX) === 0
                && (int) ($counts['conflict'] ?? PHP_INT_MAX) === 0
                && (int) ($counts['unchanged'] ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function stageCGroupPoliciesReady(): bool
    {
        if (! Schema::hasTable('membership_dimensions') || ! Schema::hasTable('group_creation_policies') || ! Schema::hasTable('governance_capability_policies')) {
            return false;
        }
        try {
            $dimensions = MembershipDimension::query()->where('enabled', true)->whereIn('key', self::STAGE_C_DIMENSIONS)->get()->keyBy('key');
            if ($dimensions->count() !== count(self::STAGE_C_DIMENSIONS)) {
                return false;
            }
            foreach (self::STAGE_C_DIMENSIONS as $dimensionKey) {
                $dimension = $dimensions->get($dimensionKey);
                $policy = GroupCreationPolicy::query()
                    ->where('membership_dimension_id', $dimension->id)
                    ->where('enabled', true)
                    ->whereNull('governance_area_id')
                    ->whereNull('governance_type')
                    ->whereNull('governance_rank')
                    ->where('priority', 0)->first();
                if ($policy === null || $policy->mode?->value !== 'automatic' || ($policy->metadata['stage_c_canonical_groups'] ?? false) !== true) {
                    return false;
                }
            }
            $capabilityPolicy = GovernanceCapabilityPolicy::query()->where('scope', 'default')->whereNull('country_code')->whereNull('governance_type')->first();
            return $capabilityPolicy !== null && ($capabilityPolicy->capabilities['group_creation_mode'] ?? null) === 'automatic';
        } catch (Throwable) {
            return false;
        }
    }

    private function rolloutFlagsAreBoolean(): bool
    {
        foreach (['runtime_enabled', 'registration_enabled', 'groups_enabled', 'elections_enabled', 'projects_enabled'] as $key) {
            if (! is_bool(config("location-governance.{$key}"))) {
                return false;
            }
        }
        return true;
    }

    private function hasFatalProposalConflicts(): bool
    {
        if (! Schema::hasTable('location_proposals')) {
            return false;
        }
        try {
            return DB::table('location_proposals')
                ->whereIn('status', ['pending', 'ready_for_review', 'needs_evidence'])
                ->get(['metadata'])
                ->contains(function (object $proposal): bool {
                    $metadata = $proposal->metadata;
                    if (is_string($metadata)) {
                        $metadata = json_decode($metadata, true);
                    }
                    return is_array($metadata) && ($metadata['fatal_conflict'] ?? false) === true;
                });
        } catch (Throwable) {
            return true;
        }
    }

    private function check(array &$checks, string $name, bool $passed, string $failure): void
    {
        $checks[] = ['name' => $name, 'passed' => $passed, 'failure' => $passed ? null : $failure];
    }
}
