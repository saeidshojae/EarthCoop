<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NajmBaharRecalculateBalances extends Command
{
    protected $signature = 'najm-bahar:recalculate-balances {--all : Deprecated compatibility option}';

    protected $description = 'Deprecated: ledger-only balance recalculation is unsafe with active/dim monetary buckets.';

    public function handle(): int
    {
        $this->error('This command is disabled because recalculating one aggregate balance from ledger amounts can corrupt active/dim state.');
        $this->line('Use `najm-bahar:audit-balances` first, then `najm-bahar:normalize-balances` for canonical local-balance normalization.');

        return self::FAILURE;
    }
}
