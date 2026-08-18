<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\MonetaryRetirementLiability;
use App\Modules\NajmBahar\Services\RetirementLiabilitySettlementService;
use Illuminate\Console\Command;

class NajmBaharSettleRetirementLiabilities extends Command
{
    protected $signature = 'najm-bahar:settle-retirement-liabilities {--id=}';

    protected $description = 'Settle outstanding monetary retirement liabilities from protected treasury surplus.';

    public function handle(RetirementLiabilitySettlementService $service): int
    {
        $query = MonetaryRetirementLiability::query()
            ->where('status', 'outstanding')
            ->whereColumn('settled_amount', '<', 'amount')
            ->orderBy('id');

        if ($id = $this->option('id')) {
            $query->whereKey((int) $id);
        }

        $processed = 0;
        $settled = 0;

        $query->chunkById(100, function ($liabilities) use ($service, &$processed, &$settled) {
            foreach ($liabilities as $liability) {
                $before = max(0, (int) $liability->amount - (int) $liability->settled_amount);
                $result = $service->settle((int) $liability->id);
                $after = max(0, (int) $result->amount - (int) $result->settled_amount);
                $processed++;
                $settled += max(0, $before - $after);

                $this->line(sprintf(
                    'liability=%d before=%d after=%d status=%s',
                    $result->id,
                    $before,
                    $after,
                    $result->status
                ));
            }
        });

        $this->info("Processed {$processed} liabilities; destroyed {$settled} Gol against outstanding retirement obligations.");

        return self::SUCCESS;
    }
}
