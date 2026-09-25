<?php

namespace App\Console\Commands;

use App\Services\LocationGovernance\Import\IranSettlementCatalogImporter;
use Illuminate\Console\Command;
use Throwable;

final class Iran1404SettlementCatalogCommand extends Command
{
    protected $signature = 'location:iran-1404-settlement-catalog
        {--dry-run : Validate pinned source and report the catalog diff without writes}
        {--apply : Apply only neutral, non-authorizing settlement rows}
        {--confirm= : Exact environment-specific confirmation required for apply}';

    protected $description = 'Dry-run or apply the pinned Iran 1404 neutral settlement catalog';

    public function handle(IranSettlementCatalogImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Choose exactly one of --dry-run or --apply.');
            return self::FAILURE;
        }

        $allowProduction = false;
        if ($apply) {
            $confirmation = (string) $this->option('confirm');
            $authorizedLocal = app()->environment(['local', 'testing'])
                && $confirmation === 'APPLY-IR-SETTLEMENT-CATALOG-ISOLATED';
            $allowProduction = app()->environment('production')
                && $confirmation === 'APPLY-IR-SETTLEMENT-CATALOG-PRODUCTION';

            if (! $authorizedLocal && ! $allowProduction) {
                $this->error('Iran 1404 settlement catalog apply requires the exact environment-specific confirmation token.');
                return self::FAILURE;
            }
        }

        try {
            $result = $importer->importPinnedSource($apply, $allowProduction);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($result as $name => $value) {
            $this->line($name.': '.$value);
        }

        return self::SUCCESS;
    }
}
