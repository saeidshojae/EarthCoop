<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HistoricalLedgerBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_ledger_or_change_balances(): void
    {
        [$from, $to, $transaction] = $this->makeHistoricalTransfer();

        Artisan::call('najm-bahar:backfill-ledger');

        $this->assertSame(0, LedgerEntry::where('transaction_id', $transaction->id)->count());
        $this->assertSame(700, (int) $from->fresh()->balance_active);
        $this->assertSame(300, (int) $to->fresh()->balance_active);
    }

    public function test_apply_backfills_entries_without_changing_balances_and_is_idempotent(): void
    {
        [$from, $to, $transaction] = $this->makeHistoricalTransfer();

        Artisan::call('najm-bahar:backfill-ledger', ['--apply' => true]);

        $entries = LedgerEntry::where('transaction_id', $transaction->id)->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(-300, (int) $entries[0]->amount);
        $this->assertSame(300, (int) $entries[1]->amount);
        $this->assertSame('active', data_get($entries[0]->meta, 'balance_bucket'));
        $this->assertTrue((bool) data_get($entries[0]->meta, 'historical_backfill'));

        $this->assertSame(700, (int) $from->fresh()->balance_active);
        $this->assertSame(300, (int) $to->fresh()->balance_active);

        Artisan::call('najm-bahar:backfill-ledger', ['--apply' => true]);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $transaction->id)->count());
    }

    private function makeHistoricalTransfer(): array
    {
        $from = Account::create([
            'account_number' => 'TEST-001',
            'name' => 'From',
            'type' => 'user',
            'balance' => 700,
            'balance_active' => 700,
            'balance_faded' => 0,
        ]);

        $to = Account::create([
            'account_number' => 'TEST-002',
            'name' => 'To',
            'type' => 'user',
            'balance' => 300,
            'balance_active' => 300,
            'balance_faded' => 0,
        ]);

        $transaction = Transaction::create([
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 300,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['balance_type' => 'active'],
            'description' => 'Historical transfer missing ledger entries',
        ]);

        return [$from, $to, $transaction];
    }
}
