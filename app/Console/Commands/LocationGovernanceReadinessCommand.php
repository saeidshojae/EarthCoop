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
        $this->check(
            $checks,
            'reference import status',
            $import !== null && $import->status === 'completed',
            'target reference import is not completed'
        );
        $this->check(
            $checks,
            'reference import conflicts',
            $import !== null && (int) $import->conflicts === 0 && $this->importConflictRows($import->id) === 0,
            'reference import conflicts remain unresolved'
        );
        $this->check(
            $checks,
            'reference traversal',
            $this->referenceTraversalReady(),
            'target reference root is missing, inactive, or has no active schema-valid child'
        );

        $this->check($checks, 'governance mappings', $this->hasGovernanceMappings(), 'canonical governance mappings are missing');
        $this->check(
            $checks,
            'governance topology',
            $this->governanceTopologyReady(),
            'reviewed reference governance topology is missing, conflicting, or not idempotent'
        );
        $this->check(
            $checks,
            'group creation policies',
            $this->groupCreationPoliciesReady(),
            'canonical group creation policies are missing or not activated for Stage C'
        );
        $this->check($checks, 'rollout flags', $this->flagsReady(), 'required rollout flags are not enabled');
        $this->check($checks, 'release evidence', $this->releaseEvidenceReady(), 'release evidence is missing or stale');

        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $this->allChecksPass($checks), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $check) {
                $this->line(sprintf('[%s] %s%s', $check['ok'] ? 'PASS' : 'FAIL', $check['name'], $check['ok'] ? '' : ': '.$check['message']));
            }
        }

        return $this->allChecksPass($checks) ? self::SUCCESS : self::FAILURE;
    }

    private function check(array &$checks, string $name, bool $ok, string $message): void
    {
        $checks[] = compact('name', 'ok', 'message');
    }

    private function allChecksPass(array $checks): bool
    {
        return collect($checks)->every(fn (array $check) => $check['ok']);
    }

    private function requiredMigrationsReady(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();

        return collect(self::REQUIRED_MIGRATIONS)->every(fn (string $migration) => in_array($migration, $ran, true));
    }

    private function targetSchemaReady(): bool
    {
        if (! Schema::hasTable('location_schemas')) {
            return false;
        }

        return DB::table('location_schemas')->where('is_active', true)->exists();
    }

    private function latestTargetImport(): ?object
    {
        if (! Schema::hasTable('location_import_runs')) {
            return null;
        }

        return DB::table('location_import_runs')->latest('id')->first();
    }

    private function importConflictRows(int $runId): int
    {
        if (! Schema::hasTable('location_import_conflicts')) {
            return 0;
        }

        return DB::table('location_import_conflicts')->where('location_import_run_id', $runId)->count();
    }

    private function referenceTraversalReady(): bool
    {
        if (! Schema::hasTable('locations') || ! Schema::hasTable('location_type_relations')) {
            return false;
        }

        $root = DB::table('locations')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->whereNotNull('location_schema_id')
            ->whereNotNull('location_type_id')
            ->first();

        if (! $root) {
            return false;
        }

        $allowedChildTypeIds = DB::table('location_type_relations')
            ->where('location_schema_id', $root->location_schema_id)
            ->where('parent_type_id', $root->location_type_id)
            ->pluck('child_type_id');

        if ($allowedChildTypeIds->isEmpty()) {
            return false;
        }

        return DB::table('locations')
            ->where('parent_id', $root->id)
            ->where('status', 'active')
            ->where('location_schema_id', $root->location_schema_id)
            ->whereIn('location_type_id', $allowedChildTypeIds)
            ->exists();
    }

    private function hasGovernanceMappings(): bool
    {
        return Schema::hasTable('governance_area_locations') && DB::table('governance_area_locations')->exists();
    }

    private function governanceTopologyReady(): bool
    {
        try {
            return app(ReferenceGovernanceTopologyImporter::class)->readinessReady();
        } catch (Throwable) {
            return false;
        }
    }

    private function groupCreationPoliciesReady(): bool
    {
        if (! Schema::hasTable('membership_dimensions') || ! Schema::hasTable('group_creation_policies')) {
            return false;
        }

        foreach (self::STAGE_C_DIMENSIONS as $key) {
            $dimension = MembershipDimension::query()->where('key', $key)->first();
            if (! $dimension) {
                return false;
            }

            $policy = GroupCreationPolicy::query()
                ->where('membership_dimension_id', $dimension->id)
                ->whereNull('country_code')
                ->whereNull('governance_type_key')
                ->first();

            if (! $policy || $policy->creation_mode !== 'automatic_on_membership') {
                return false;
            }
        }

        return true;
    }

    private function flagsReady(): bool
    {
        return (bool) config('features.location_governance.canonical_runtime')
            && (bool) config('features.location_governance.canonical_registration')
            && (bool) config('features.location_governance.canonical_groups')
            && (bool) config('features.location_governance.canonical_elections')
            && (bool) config('features.location_governance.canonical_project_scope');
    }

    private function releaseEvidenceReady(): bool
    {
        $evidence = config('location-governance.release_evidence');

        return is_array($evidence)
            && ! empty($evidence['release_id'])
            && ! empty($evidence['validated_at'])
            && ! empty($evidence['validation_run']);
    }
}
