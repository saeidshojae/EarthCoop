<?php

namespace App\Console\Commands;

use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Illuminate\Console\Command;
use Throwable;

class LocationReferenceImportCommand extends Command
{
    protected $signature = 'location:reference-import
                            {country : ISO 3166-1 alpha-2 country code}
                            {--dataset-version=v1 : Reference dataset version}
                            {--dry-run : Report the diff without any writes}
                            {--apply : Apply the versioned reference dataset}';

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
