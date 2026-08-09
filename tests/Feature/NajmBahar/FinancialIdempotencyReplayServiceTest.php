<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\FinancialIdempotencyReplayService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialIdempotencyReplayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_constraint_loser_replays_visible_winning_transaction(): void
    {
        $account = Account::create([
            'account_number' => 'IDEM-REPLAY-001',
            'name' => 'Replay target',
            'type' => 'system',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
            'committed_dim' => 0,
        ]);

        $winner = Transaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 25,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['idempotency_key' => 'race-replay-key-1'],
            'description' => 'Winning request',
        ]);

        try {
            Transaction::create([
                'from_account_id' => null,
                'to_account_id' => $account->id,
                'amount' => 25,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => ['idempotency_key' => 'race-replay-key-1'],
                'description' => 'Losing concurrent request',
            ]);
            $this->fail('Expected the canonical idempotency unique constraint to reject the loser.');
        } catch (QueryException $exception) {
            $replayed = app(FinancialIdempotencyReplayService::class)
                ->replayAfterUniqueConflict('race-replay-key-1', $exception);
        }

        $this->assertSame((int) $winner->id, (int) $replayed->id);
        $this->assertSame(1, Transaction::where('idempotency_key', 'race-replay-key-1')->count());
    }

    public function test_find_supports_legacy_metadata_only_key(): void
    {
        $account = Account::create([
            'account_number' => 'IDEM-REPLAY-LEGACY',
            'name' => 'Legacy replay target',
            'type' => 'system',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
            'committed_dim' => 0,
        ]);

        $transaction = Transaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 10,
            'type' => 'immediate',
            'status' => 'completed',
            'description' => 'Legacy metadata-only fixture',
        ]);

        Transaction::whereKey($transaction->id)->update([
            'metadata' => json_encode(['idempotency_key' => 'legacy-replay-key-1']),
            'idempotency_key' => null,
        ]);

        $found = app(FinancialIdempotencyReplayService::class)->find('legacy-replay-key-1');

        $this->assertNotNull($found);
        $this->assertSame((int) $transaction->id, (int) $found->id);
    }
}
