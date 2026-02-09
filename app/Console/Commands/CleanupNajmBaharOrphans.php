<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\NajmBahar\Models\Account as NajmAccount;
use App\Modules\NajmBahar\Models\SubAccount as NajmSubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Models\LedgerEntry as NajmLedgerEntry;
use App\Models\User;

class CleanupNajmBaharOrphans extends Command
{
    protected $signature = 'najm-bahar:cleanup-orphans {--dry-run : Show counts without deleting data}';
    protected $description = 'Remove orphan Najm Bahar user accounts and related data, then recalculate balances.';

    public function handle(): int
    {
        $orphanAccountIds = NajmAccount::where('type', 'user')
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhereNotIn('user_id', User::select('id'));
            })
            ->pluck('id');

        if ($orphanAccountIds->isEmpty()) {
            $this->info('No orphan Najm Bahar accounts found.');
            return 0;
        }

        $transactionIds = NajmTransaction::whereIn('from_account_id', $orphanAccountIds)
            ->orWhereIn('to_account_id', $orphanAccountIds)
            ->pluck('id');

        $this->info('Orphan accounts: ' . $orphanAccountIds->count());
        $this->info('Related transactions: ' . $transactionIds->count());

        if ($this->option('dry-run')) {
            $this->info('Dry run enabled. No data was deleted.');
            return 0;
        }

        DB::transaction(function () use ($orphanAccountIds, $transactionIds) {
            if ($transactionIds->isNotEmpty()) {
                NajmLedgerEntry::whereIn('transaction_id', $transactionIds)->delete();
                NajmTransaction::whereIn('id', $transactionIds)->delete();
            }

            NajmSubAccount::whereIn('account_id', $orphanAccountIds)->delete();
            NajmAccount::whereIn('id', $orphanAccountIds)->delete();

            $balances = NajmLedgerEntry::select('account_id', DB::raw('SUM(amount) as balance'))
                ->groupBy('account_id')
                ->get();

            NajmAccount::query()->update(['balance' => 0]);

            foreach ($balances as $row) {
                NajmAccount::where('id', $row->account_id)->update([
                    'balance' => (int) $row->balance,
                ]);
            }
        });

        $this->info('Cleanup completed.');

        return 0;
    }
}
