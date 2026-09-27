<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharTransactionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_owner_scoped_integer_projected_and_hides_internal_metadata(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $owned = $this->userAccount($user, 'Owner');
        $foreign = $this->userAccount($other, 'Other');
        $system = $this->systemAccount();

        $visible = $this->recordTransaction(
            $system,
            $owned,
            4_500_000_123,
            'adjustment',
            'completed',
            'active',
            'Initial active funding',
        );
        $this->recordTransaction(
            $foreign,
            $system,
            999,
            'fee',
            'completed',
            'active',
            'Foreign-only transaction',
        );

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?page[limit]=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.type', 'adjustment')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.amount_gol', 4_500_000_123)
            ->assertJsonPath('data.0.balance_bucket', 'active')
            ->assertJsonPath('data.0.direction', 'incoming')
            ->assertJsonPath('data.0.counterparty.account_number', $system->account_number)
            ->assertJsonPath('data.0.description', 'Initial active funding')
            ->assertJsonPath('data.0.tracking_number', $visible->tracking_number)
            ->assertJsonPath('meta.pagination.has_more', false)
            ->assertJsonPath('meta.pagination.next_cursor', null)
            ->assertJsonMissingPath('data.0.metadata')
            ->assertJsonMissingPath('data.0.idempotency_key')
            ->assertJsonMissingPath('data.0.from_account_id')
            ->assertJsonMissingPath('data.0.to_account_id')
            ->assertJsonMissingPath('data.0.counterparty.user_id')
            ->assertJsonMissingPath('data.0.secret_token');

        $this->assertIsInt($response->json('data.0.amount_gol'));
    }

    public function test_cursor_history_orders_newest_first_without_duplicates_across_pages(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $owned = $this->userAccount($user, 'Owner');
        $system = $this->systemAccount();

        $first = $this->recordTransaction($system, $owned, 100, 'adjustment', 'completed', 'active', 'first');
        $second = $this->recordTransaction($system, $owned, 200, 'adjustment', 'completed', 'active', 'second');
        $third = $this->recordTransaction($owned, $system, 300, 'fee', 'completed', 'active', 'third');

        $pageOne = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?page[limit]=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $third->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('meta.pagination.has_more', true);

        $cursor = $pageOne->json('meta.pagination.next_cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);

        $query = http_build_query([
            'page' => [
                'cursor' => $cursor,
                'limit' => 2,
            ],
        ]);

        $pageTwo = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.pagination.has_more', false);

        $ids = array_merge(
            array_column($pageOne->json('data'), 'id'),
            array_column($pageTwo->json('data'), 'id'),
        );

        $this->assertSame([$third->id, $second->id, $first->id], $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    public function test_filters_are_whitelisted_and_account_scope_must_belong_to_current_user(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create(['is_system' => false]);

        $owned = $this->userAccount($user, 'Owner');
        $foreign = $this->userAccount($other, 'Other');
        $system = $this->systemAccount();

        $matching = $this->recordTransaction($owned, $system, 120, 'fee', 'completed', 'active', 'membership');
        $this->recordTransaction($system, $owned, 220, 'adjustment', 'pending', 'dim', 'pending activation');

        $query = http_build_query([
            'filter' => [
                'type' => 'fee',
                'status' => 'completed',
                'account_id' => $owned->id,
            ],
        ]);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.direction', 'outgoing');

        $foreignQuery = http_build_query(['filter' => ['account_id' => $foreign->id]]);
        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?'.$foreignQuery)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $unsupported = http_build_query(['filter' => ['metadata' => 'secret']]);
        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?'.$unsupported)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    private function recordTransaction(
        Account $from,
        Account $to,
        int $amount,
        string $type,
        string $status,
        string $balanceType,
        string $description,
    ): Transaction {
        $metadata = [
            'balance_type' => $balanceType === 'dim' ? 'faded' : $balanceType,
            'secret_token' => 'must-not-leak',
            'internal_policy' => ['authority' => true],
        ];

        $transaction = Transaction::create([
            'idempotency_key' => 'query-fixture-'.bin2hex(random_bytes(8)),
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => $amount,
            'type' => $type,
            'status' => $status,
            'metadata' => $metadata,
            'description' => $description,
        ]);

        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $from->id,
            'amount' => -$amount,
            'entry_type' => 'debit',
            'meta' => $metadata,
        ]);
        LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'account_id' => $to->id,
            'amount' => $amount,
            'entry_type' => 'credit',
            'meta' => $metadata,
        ]);

        return $transaction->fresh();
    }

    private function userAccount(User $user, string $name): Account
    {
        return Account::create([
            'account_number' => AccountNumberService::makeMainAccountNumberForUser((int) $user->id),
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'user',
            'balance' => 10_000,
            'balance_active' => 8_000,
            'balance_faded' => 2_000,
            'committed_dim' => 0,
            'status' => 1,
        ]);
    }

    private function systemAccount(): Account
    {
        return Account::firstOrCreate(
            ['account_number' => AccountNumberService::makeSystemAccountNumber()],
            [
                'name' => 'System',
                'type' => 'system',
                'balance' => 100_000,
                'balance_active' => 100_000,
                'balance_faded' => 0,
                'committed_dim' => 0,
                'status' => 1,
            ],
        );
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-history-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => false,
        ])->assertCreated();

        return [$user, (string) $login->json('data.token'), (string) $login->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
