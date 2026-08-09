<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NajmBaharBackfillLedger extends Command
{
    protected $signature = 'najm-bahar:backfill-ledger
                            {--apply : Write missing ledger entries. Without this flag the command is read-only.}
                            {--chunk=500 : Number of transactions processed per chunk}';

    protected $description = 'Backfill missing Najm Bahar ledger entries without changing any account balance';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $missing = 0;
        $created = 0;

        Transaction::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($transactions) use ($apply, &$missing, &$created) {
                foreach ($transactions as $transaction) {
                    if (LedgerEntry::where('transaction_id', $transaction->id)->exists()) {
                        continue;
                    }

                    $entries = $this->expectedEntries($transaction);
                    if ($entries === []) {
                        continue;
                    }

                    $missing += count($entries);

                    if (! $apply) {
                        continue;
                    }

                    DB::transaction(function () use ($transaction, $entries, &$created) {
                        if (LedgerEntry::where('transaction_id', $transaction->id)->lockForUpdate()->exists()) {
                            return;
                        }

                        foreach ($entries as $entry) {
                            LedgerEntry::create($entry);
                            $created++;
                        }
                    });
                }
            });

        if (! $apply) {
            $this->info("Dry run: {$missing} missing ledger entries would be created. No balances were changed.");
            $this->warn('Run again with --apply only after reviewing the dry-run result.');
            return self::SUCCESS;
        }

        $this->info("Created {$created} historical ledger entries. Account balances were not modified.");

        return self::SUCCESS;
    }

    private function expectedEntries(Transaction $transaction): array
    {
        $amount = (int) $transaction->amount;
        if ($amount <= 0) {
            return [];
        }

        $metadata = (array) ($transaction->metadata ?? []);
        $baseMeta = array_merge($metadata, [
            'historical_backfill' => true,
            'historical_transaction_id' => $transaction->id,
        ]);

        $from = $transaction->from_account_id;
        $to = $transaction->to_account_id;

        // Historical bucket-to-bucket transition on the same account.
        if ($from && $to && (int) $from === (int) $to
            && ! empty($metadata['from_balance_type'])
            && ! empty($metadata['to_balance_type'])) {
            return [
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $from,
                    'amount' => -$amount,
                    'entry_type' => 'debit',
                    'meta' => array_merge($baseMeta, ['balance_bucket' => $metadata['from_balance_type']]),
                ],
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $to,
                    'amount' => $amount,
                    'entry_type' => 'credit',
                    'meta' => array_merge($baseMeta, ['balance_bucket' => $metadata['to_balance_type']]),
                ],
            ];
        }

        $bucket = $metadata['balance_type']
            ?? $metadata['money_state']
            ?? $metadata['to_balance_type']
            ?? 'legacy_unknown';

        $entries = [];
        if ($from) {
            $entries[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $from,
                'amount' => -$amount,
                'entry_type' => 'debit',
                'meta' => array_merge($baseMeta, ['balance_bucket' => $metadata['from_balance_type'] ?? $bucket]),
            ];
        }

        if ($to) {
            $entries[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $to,
                'amount' => $amount,
                'entry_type' => 'credit',
                'meta' => array_merge($baseMeta, ['balance_bucket' => $metadata['to_balance_type'] ?? $bucket]),
            ];
        }

        return $entries;
    }
}
