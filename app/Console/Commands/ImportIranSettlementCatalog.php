<?php

namespace App\Console\Commands;

use App\Services\LocationGovernance\Import\IranSettlementCatalogImporter;
use Illuminate\Console\Command;
use Throwable;

final class ImportIranSettlementCatalog extends Command
{
    protected $signature = 'location:iran-settlement-catalog
        {review : Full path to generated settlements.review.jsonl}
        {manifest : Full path to generated manifest.json}
        {--apply : Import into a disposable geo_uat database}
        {--confirm= : Exact confirmation for isolated import}';

    protected $description = 'Validate or import the neutral Iran settlement catalog (no operational residence or governance)';

    public function handle(IranSettlementCatalogImporter $importer): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && $this->option('confirm') !== 'APPLY-IR-SETTLEMENT-CATALOG-ISOLATED') {
            $this->error('Isolated import requires --confirm=APPLY-IR-SETTLEMENT-CATALOG-ISOLATED.');
            return self::FAILURE;
        }
        try {
            $result = $importer->import(
                (string) $this->argument('review'),
                (string) $this->argument('manifest'),
                $apply,
            );
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
