<?php

namespace App\Console\Commands;

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
            $applied = DB::table('migrations')
                ->whereIn('migration', self::REQUIRED_MIGRATIONS)
                ->pluck('migration')
                ->all();

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
                ->latest('id')
                ->first();
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
            // A broken proposal read is itself unsafe for cutover, so fail closed.
            return true;
        }
    }

    private function check(array &$checks, string $name, bool $passed, string $failure): void
    {
        $checks[] = [
            'name' => $name,
            'passed' => $passed,
            'failure' => $passed ? null : $failure,
        ];
    }
}
