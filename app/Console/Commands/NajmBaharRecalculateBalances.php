<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NajmBaharRecalculateBalances extends Command
{
    protected $signature = 'najm-bahar:recalculate-balances {--all : Also zero balances for accounts with no ledger entries}';

    protected $description = 'Recalculate NajmBahar account balances from ledger entries.';

    public function handle(): int
    {
        $totals = DB::table('najm_ledger_entries')
            ->select('account_id', DB::raw('SUM(amount) as total'))
            ->groupBy('account_id')
            ->get();

        $updated = 0;

        foreach ($totals as $row) {
            $account = Account::find($row->account_id);
            if (! $account) {
                continue;
            }

            $account->balance = (int) $row->total;
            $account->save();
            $updated++;
        }

        if ($this->option('all')) {
            $ledgerAccountIds = $totals->pluck('account_id')->all();
            Account::whereNotIn('id', $ledgerAccountIds)->update(['balance' => 0]);
        }

        $this->info("Recalculated balances for {$updated} accounts.");

        return self::SUCCESS;
    }
}
