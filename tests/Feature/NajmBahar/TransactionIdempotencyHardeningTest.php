<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionIdempotencyHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_copies_metadata_idempotency_key_to_canonical_column(): void
    {
        $account = $this->account('IDEM-CANONICAL-TO');

        $transaction = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 10,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['idempotency_key' => 'canonical-idem-key-1'],
        ]);

        $this->assertTrue(Schema::hasColumn('najm_transactions', 'idempotency_key'));
        $this->assertSame('canonical-idem-key-1', $transaction->fresh()->idempotency_key);
    }

    public function test_database_rejects_duplicate_canonical_idempotency_key(): void
    {
        $account = $this->account('IDEM-UNIQUE-TO');

        NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 10,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['idempotency_key' => 'canonical-idem-key-duplicate'],
        ]);

        $this->expectException(QueryException::class);

        NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 20,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['idempotency_key' => 'canonical-idem-key-duplicate'],
        ]);
    }

    public function test_transactions_without_idempotency_key_remain_allowed(): void
    {
        $account = $this->account('IDEM-NULL-TO');

        $first = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 10,
            'type' => 'immediate',
            'status' => 'completed',
        ]);
        $second = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => $account->id,
            'amount' => 20,
            'type' => 'immediate',
            'status' => 'completed',
        ]);

        $this->assertNull($first->fresh()->idempotency_key);
        $this->assertNull($second->fresh()->idempotency_key);
        $this->assertNotSame((int) $first->id, (int) $second->id);
    }

    private function account(string $number): Account
    {
        return Account::create([
            'account_number' => $number,
            'name' => $number,
            'type' => 'system',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
            'committed_dim' => 0,
        ]);
    }
}
