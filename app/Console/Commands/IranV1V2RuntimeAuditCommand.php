<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IranV1V2RuntimeAuditCommand extends Command
{
    protected $signature = 'location:iran-v1-v2-runtime-audit {--json : Emit machine-readable JSON only}';
    protected $description = 'Read-only inventory of current IR v1 Location dependencies before any v2 cutover';

    public function handle(): int
    {
        $map = (array) config('iran_v1_v2_crosswalk.mappings', []);
        $source = (string) config('iran_v1_v2_crosswalk.source', 'earthcoop-reference');
        $version = (string) config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1');

        if ($map === [] || ! Schema::hasTable('location_external_ids')) {
            $this->error('Reviewed crosswalk config or canonical location identity table is missing.');
            return self::FAILURE;
        }

        $identities = DB::table('location_external_ids')
            ->where('source', $source)
            ->where('dataset_version', $version)
            ->whereIn('external_id', array_keys($map))
            ->get(['location_id', 'external_id'])
            ->keyBy('external_id');

        $rows = [];
        foreach ($map as $v1ExternalId => $mapping) {
            $identity = $identities->get($v1ExternalId);
            $locationId = $identity?->location_id ? (int) $identity->location_id : null;
            $rows[] = [
                'v1_external_id' => $v1ExternalId,
                'candidate_v2_external_id' => (string) $mapping['v2'],
                'mapping_status' => (string) $mapping['status'],
                'location_id' => $locationId,
                'present' => $locationId !== null,
                'dependencies' => $locationId === null ? $this->emptyDependencies() : $this->dependencies($locationId),
            ];
        }

        $allV1 = DB::table('location_external_ids')
            ->where('source', $source)
            ->where('dataset_version', $version)
            ->count();
        $mappedPresent = collect($rows)->where('present', true)->count();
        $unreviewedPresent = max(0, $allV1 - $mappedPresent);
        $dependencyTotal = collect($rows)->sum(function (array $row): int {
            return array_sum($row['dependencies']);
        });
        $mappedDependencyRows = collect($rows)->filter(function (array $row): bool {
            return $row['present'] && array_sum($row['dependencies']) > 0;
        })->count();
        $blocked = collect($rows)->contains(fn (array $row): bool =>
            $row['mapping_status'] !== 'verified_identity' && $row['present']
        );

        $report = [
            'mode' => 'read_only',
            'database' => (string) DB::connection()->getDatabaseName(),
            'source' => $source,
            'v1_dataset_version' => $version,
            'v1_identity_count' => $allV1,
            'reviewed_mapping_count' => count($map),
            'reviewed_mapping_present' => $mappedPresent,
            'unreviewed_v1_identity_count' => $unreviewedPresent,
            'dependency_count_total' => $dependencyTotal,
            'mapped_dependency_rows' => $mappedDependencyRows,
            'shared_cutover_blocked' => $blocked || $unreviewedPresent > 0 || $dependencyTotal > 0,
            'rows' => $rows,
            'warning' => 'Audit only. This command performs no UPDATE/DELETE/INSERT and is not migration authorization.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        $this->info('Iran v1 -> v2 runtime audit (READ ONLY)');
        $this->line('v1 identities: '.$allV1);
        $this->line('reviewed mappings present: '.$mappedPresent.'/'.count($map));
        $this->line('unreviewed v1 identities: '.$unreviewedPresent);
        $this->line('dependency references: '.$dependencyTotal.' across '.$mappedDependencyRows.' mapped rows');
        $this->line('shared cutover blocked: '.($report['shared_cutover_blocked'] ? 'YES' : 'NO'));
        foreach ($rows as $row) {
            $deps = $row['dependencies'];
            $this->line(sprintf(
                '%s -> %s [%s] location=%s deps(residence=%d proposals=%d governance=%d groups=%d)',
                $row['v1_external_id'],
                $row['candidate_v2_external_id'],
                $row['mapping_status'],
                $row['location_id'] ?? 'missing',
                $deps['user_location_relationships'],
                $deps['location_proposals'],
                $deps['governance_area_locations'],
                $deps['groups_via_governance'],
            ));
        }
        $this->warn($report['warning']);
        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function dependencies(int $locationId): array
    {
        $governanceIds = Schema::hasTable('governance_area_locations')
            ? DB::table('governance_area_locations')->where('location_id', $locationId)->pluck('governance_area_id')
            : collect();

        return [
            'user_location_relationships' => Schema::hasTable('user_location_relationships')
                ? DB::table('user_location_relationships')->where('location_id', $locationId)->count()
                : 0,
            'location_proposals' => Schema::hasTable('location_proposals')
                ? DB::table('location_proposals')->where(function ($query) use ($locationId): void {
                    $query->where('parent_location_id', $locationId)->orWhere('resolved_location_id', $locationId);
                })->count()
                : 0,
            'governance_area_locations' => $governanceIds->count(),
            'groups_via_governance' => Schema::hasTable('groups') && Schema::hasColumn('groups', 'governance_area_id') && $governanceIds->isNotEmpty()
                ? DB::table('groups')->whereIn('governance_area_id', $governanceIds)->count()
                : 0,
        ];
    }

    /** @return array<string, int> */
    private function emptyDependencies(): array
    {
        return [
            'user_location_relationships' => 0,
            'location_proposals' => 0,
            'governance_area_locations' => 0,
            'groups_via_governance' => 0,
        ];
    }
}
