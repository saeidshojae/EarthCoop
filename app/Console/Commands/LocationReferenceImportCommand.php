<?php

namespace App\Console\Commands;

use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\UserLocationRelationship;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Throwable;

class LocationReferenceImportCommand extends Command
{
    protected $signature = 'location:reference-import
                            {country : ISO 3166-1 alpha-2 country code}
                            {--dataset-version=v1 : Reference dataset version}
                            {--dry-run : Report the diff without any writes}
                            {--apply : Apply the versioned reference dataset}
                            {--confirm= : Explicit confirmation required for isolated IR v2 apply}';

    protected $description = 'Diff or apply a versioned canonical reference geography dataset';

    public function handle(ReferenceGeographyImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun && $apply) {
            $this->error('Choose either --dry-run or --apply, not both.');
            return self::INVALID;
        }

        // Safe default: an omitted mode behaves as dry-run rather than mutating data.
        $apply = $apply && ! $dryRun;

        // A v2 candidate must never be imported into the UAT v1 database,
        // an existing member database or Production. Dry-run remains available.
        if ($apply && strtoupper(trim((string) $this->argument('country'))) === 'IR'
            && trim((string) $this->option('dataset-version')) === 'v2') {
            $confirmation = (string) $this->option('confirm');
            if (! app()->environment(['local', 'testing'])
                || ! in_array($confirmation, ['APPLY-IR-1404-V2-ISOLATED', 'APPLY-IR-1404-V2-UAT'], true)) {
                $this->error('IR v2 apply requires an explicit local/testing confirmation token.');
                return self::FAILURE;
            }

            if ($confirmation === 'APPLY-IR-1404-V2-ISOLATED') {
                $databaseName = (string) DB::connection()->getDatabaseName();
                $isolatedName = preg_match('/(?:^|[_-])geo[_-]uat(?:$|[_-])/i', $databaseName) === 1
                    || (app()->environment('testing')
                        && ($databaseName === ':memory:' || str_contains(strtolower($databaseName), 'test')));
                if (! $isolatedName) {
                    $this->error('IR v2 isolated apply requires an isolated geo_uat database name.');
                    return self::FAILURE;
                }
                if (Location::query()->where('country_code', 'IR')
                        ->whereDoesntHave('schema', fn ($query) => $query->where('key', 'ir-reference-v2')->where('version', 'v2'))
                        ->exists()
                    || UserLocationRelationship::query()->exists()
                    || GovernanceArea::query()->where('country_code', 'IR')->exists()) {
                    $this->error('IR v2 isolated apply refused: legacy/non-v2 geography, residence history, or governance areas exist.');
                    return self::FAILURE;
                }
            }
        }

        try {
            $summary = $importer->import(
                (string) $this->argument('country'),
                (string) $this->option('dataset-version'),
                $apply,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('mode: '.($apply ? 'apply' : 'dry-run'));
        $this->line('create: '.$summary->creates);
        $this->line('update: '.$summary->updates);
        $this->line('deactivate: '.$summary->deactivates);
        $this->line('conflict: '.$summary->conflicts);
        $this->line('unchanged: '.$summary->unchanged);

        return $summary->conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }
}
