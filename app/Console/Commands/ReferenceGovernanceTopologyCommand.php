<?php

namespace App\Console\Commands;

use App\Services\LocationGovernance\Import\ReferenceGovernanceTopologyImporter;
use Illuminate\Console\Command;
use Throwable;

class ReferenceGovernanceTopologyCommand extends Command
{
    protected $signature = 'location-governance:reference-topology
        {country : ISO country code}
        {--dataset-version=v1 : Versioned reference dataset}
        {--dry-run : Compute the governance topology diff without writing}
        {--apply : Apply the explicit governance topology}';

    protected $description = 'Dry-run or apply an explicit, versioned Location-to-Governance reference topology.';

    public function handle(ReferenceGovernanceTopologyImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        if ($dryRun === $apply) {
            $this->error('Choose exactly one of --dry-run or --apply.');
            return self::FAILURE;
        }

        try {
            $country = strtoupper((string) $this->argument('country'));
            $version = (string) $this->option('dataset-version');
            $counts = $apply
                ? $importer->apply($country, $version)
                : $importer->diff($country, $version);

            $this->line('mode: '.($apply ? 'apply' : 'dry-run'));
            foreach (['create', 'update', 'conflict', 'unchanged'] as $key) {
                $this->line($key.': '.(int) ($counts[$key] ?? 0));
            }

            return ($counts['conflict'] ?? 0) === 0 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
