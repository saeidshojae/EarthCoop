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

        $this->check($checks, 'governance mappings', $this->hasGovernanceMappings(), 'canonical governance mappings are missing');
        $this->check(
            $checks,
            'governance topology',
            $this->governanceTopologyReady(),
            'reviewed reference governance topology is missing, conflicting, or not idempotent'
        );
        $this->check(
            $checks,
            'Stage C group policies',
            $this->stageCGroupPoliciesReady(),
            'canonical automatic group policies are not fully activated for Stage C'
        );
        $this->check($checks, 'rollout flags', $this->rolloutFlagsAreBoolean(), 'one or more rollout flags are invalid');
        $this->check($checks, 'proposal fatal conflicts', ! $this->hasFatalProposalConflicts(), 'fatal location proposal conflicts remain unresolved');

        $validationSha = trim((string) config('location-governance.validation_sha', ''));
        $this->check(
            $checks,
            'validation SHA',
            (bool) preg_match('/^[0-9a-f]{40}$/i', $validationSha),
            'validated release SHA evidence is missing or invalid'
        );

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
                $prefix = $check['passed'] ? '[PASS]' : '[FAIL]';
                $this->line(sprintf('%s %s%s', $prefix, $check['name'], $check['passed'] ? '' : ': '.$check['message']));
            }

            $this->newLine();
            $this->line($ready ? 'READY' : 'NOT READY');
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    private function requiredMigrationsReady(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        $applied = DB::table('migrations')
            ->whereIn('migration', self::REQUIRED_MIGRATIONS)
            ->pluck('migration')
            ->all();

        return count(array_diff(self::REQUIRED_MIGRATIONS, $applied)) === 0;
    }

    private function targetSchemaReady(): bool
    {
        if (! Schema::hasTable('location_schemas')) {
            return false;
        }

        return DB::table('location_schemas')
            ->where('country_code', config('location-governance.target_country'))
            ->where('key', config('location-governance.target_schema'))
            ->where('status', 'active')
            ->exists();
    }

    private function latestTargetImport(): ?object
    {
        if (! Schema::hasTable('location_import_runs')) {
            return null;
        }

        return DB::table('location_import_runs')
            ->where('source', config('location-governance.target_dataset_source'))
            ->where('dataset_version', config('location-governance.target_dataset_version'))
            ->where('country_code', config('location-governance.target_country'))
            ->orderByDesc('id')
            ->first();
    }

    private function importConflictRows(int $runId): int
    {
        if (! Schema::hasTable('location_import_conflicts')) {
            return PHP_INT_MAX;
        }

        return DB::table('location_import_conflicts')
            ->where('location_import_run_id', $runId)
            ->whereNull('resolved_at')
            ->count();
    }

    private function hasGovernanceMappings(): bool
    {
        return Schema::hasTable('governance_area_location')
            && DB::table('governance_area_location')->exists();
    }

    private function governanceTopologyReady(): bool
    {
        try {
            $summary = app(ReferenceGovernanceTopologyImporter::class)->dryRunSummary(
                (string) config('location-governance.target_country'),
                (string) config('location-governance.target_dataset_version')
            );

            return (int) ($summary['create'] ?? -1) === 0
                && (int) ($summary['update'] ?? -1) === 0
                && (int) ($summary['conflict'] ?? -1) === 0
                && (int) ($summary['unchanged'] ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function stageCGroupPoliciesReady(): bool
    {
        if (! Schema::hasTable('membership_dimensions')
            || ! Schema::hasTable('group_creation_policies')
            || ! Schema::hasTable('governance_capability_policies')) {
            return false;
        }

        foreach (self::STAGE_C_DIMENSIONS as $dimensionKey) {
            $dimension = MembershipDimension::query()->where('key', $dimensionKey)->first();
            if (! $dimension) {
                return false;
            }

            $policy = GroupCreationPolicy::query()
                ->where('membership_dimension_id', $dimension->id)
                ->whereNull('country_code')
                ->first();

            if (! $policy || ! $policy->enabled || $policy->creation_mode !== 'automatic') {
                return false;
            }
        }

        $defaultCapability = GovernanceCapabilityPolicy::query()
            ->whereNull('country_code')
            ->whereNull('governance_type')
            ->first();

        return $defaultCapability?->group_creation_mode === 'automatic';
    }

    private function rolloutFlagsAreBoolean(): bool
    {
        foreach (['runtime_enabled', 'registration_enabled', 'groups_enabled', 'elections_enabled', 'projects_enabled'] as $flag) {
            if (! is_bool(config('location-governance.'.$flag))) {
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

        return DB::table('location_proposals')
            ->where('status', 'conflict')
            ->exists();
    }

    private function check(array &$checks, string $name, bool $passed, string $message): void
    {
        $checks[] = compact('name', 'passed', 'message');
    }
}
