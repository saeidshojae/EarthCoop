<?php

namespace App\Console\Commands;

use App\Models\LocationExternalId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IranSettlementUatReadinessCommand extends Command
{
    protected $signature = 'location:iran-settlement-uat-readiness {--json : Emit machine-readable JSON only}';
    protected $description = 'Read-only readiness check for Iran 1404 settlement manual UAT';

    public function handle(): int
    {
        $database = (string) DB::connection()->getDatabaseName();
        $isSafeEnvironment = app()->environment(['local', 'testing']);
        $isGeoUatDatabase = preg_match('/(?:^|[_-])geo[_-]uat(?:$|[_-])/i', $database) === 1
            || (app()->environment('testing')
                && ($database === ':memory:' || str_contains(strtolower($database), 'test')));

        $requiredTables = [
            'reference_settlements',
            'reference_settlement_residence_claims',
            'reference_settlement_reviews',
            'pending_residence_intents',
            'location_scoped_group_requests',
            'location_external_ids',
        ];
        $tableStatus = collect($requiredTables)->mapWithKeys(
            fn (string $table): array => [$table => Schema::hasTable($table)]
        )->all();

        $expectedSettlements = 99317;
        $settlementCount = Schema::hasTable('reference_settlements')
            ? DB::table('reference_settlements')
                ->where('source', 'IranCountryDivisions/geo_1404')
                ->where('dataset_version', 'v2')
                ->count()
            : 0;

        $mapping = (array) config('iran_v1_v2_crosswalk.mappings', []);
        $verifiedParentMappings = collect($mapping)
            ->filter(fn (array $item): bool => ($item['status'] ?? null) === 'verified_identity');

        $verifiedV1Ids = $verifiedParentMappings->keys()->values()->all();
        $presentVerifiedV1Ids = Schema::hasTable('location_external_ids')
            ? LocationExternalId::query()
                ->where('source', (string) config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
                ->where('dataset_version', (string) config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1'))
                ->whereIn('external_id', $verifiedV1Ids)
                ->pluck('external_id')
                ->values()
                ->all()
            : [];

        $missingVerifiedV1Ids = array_values(array_diff($verifiedV1Ids, $presentVerifiedV1Ids));

        $blockers = [];
        if (! $isSafeEnvironment) {
            $blockers[] = 'APP_ENV must be local or testing.';
        }
        if (! $isGeoUatDatabase) {
            $blockers[] = 'Database name must identify an isolated geo_uat database.';
        }
        foreach ($tableStatus as $table => $present) {
            if (! $present) {
                $blockers[] = "Missing required table: {$table}.";
            }
        }
        if ($settlementCount !== $expectedSettlements) {
            $blockers[] = "Expected {$expectedSettlements} Iran v2 neutral settlements; found {$settlementCount}.";
        }
        if ($missingVerifiedV1Ids !== []) {
            $blockers[] = 'Missing reviewed v1 canonical identities: '.implode(', ', $missingVerifiedV1Ids).'.';
        }

        $warnings = [];
        if (! (bool) config('iran_settlement_catalog.enabled', false)) {
            $warnings[] = 'IR_SETTLEMENT_CATALOG_ENABLED is false; picker/search will stay hidden until enabled for UAT.';
        }
        if (! (bool) config('iran_settlement_catalog.claims_enabled', false)) {
            $warnings[] = 'IR_SETTLEMENT_CLAIMS_ENABLED is false; claim/Step 3 settlement flow will stay disabled until enabled for UAT.';
        }

        $report = [
            'mode' => 'read_only',
            'database' => $database,
            'environment' => app()->environment(),
            'safe_environment' => $isSafeEnvironment,
            'isolated_geo_uat_database' => $isGeoUatDatabase,
            'required_tables' => $tableStatus,
            'settlement_count' => $settlementCount,
            'expected_settlement_count' => $expectedSettlements,
            'verified_crosswalk_parent_count' => $verifiedParentMappings->count(),
            'present_verified_v1_identity_count' => count($presentVerifiedV1Ids),
            'missing_verified_v1_identities' => $missingVerifiedV1Ids,
            'catalog_enabled' => (bool) config('iran_settlement_catalog.enabled', false),
            'claims_enabled' => (bool) config('iran_settlement_catalog.claims_enabled', false),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'ready_for_manual_uat' => $blockers === [],
            'warning' => 'Read-only readiness check. This command performs no INSERT/UPDATE/DELETE and is not Production authorization.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $blockers === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Iran settlement manual UAT readiness (READ ONLY)');
        $this->line('database: '.$database);
        $this->line('environment: '.app()->environment());
        $this->line('settlements: '.$settlementCount.'/'.$expectedSettlements);
        $this->line('reviewed v1 identities present: '.count($presentVerifiedV1Ids).'/'.$verifiedParentMappings->count());
        $this->line('ready: '.($blockers === [] ? 'YES' : 'NO'));

        foreach ($blockers as $blocker) {
            $this->error('BLOCKER: '.$blocker);
        }
        foreach ($warnings as $warning) {
            $this->warn('WARNING: '.$warning);
        }
        $this->warn($report['warning']);

        return $blockers === [] ? self::SUCCESS : self::FAILURE;
    }
}
