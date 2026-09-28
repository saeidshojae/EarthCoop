<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharScheduledOperationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_lists_only_personal_scheduled_subaccount_operations_without_internal_payload_leakage(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Scheduled member');
        $subAccounts = app(SubAccountService::class);
        $ownFrom = $subAccounts->createSubAccount((int) $main->id, 'Own source');
        $ownTo = $subAccounts->createSubAccount((int) $main->id, 'Own destination');

        $other = User::factory()->create(['is_system' => false]);
        $otherMain = app(AccountService::class)->createMainAccountForUser((int) $other->id, 'Other member');
        $foreignFrom = $subAccounts->createSubAccount((int) $otherMain->id, 'Foreign source');
        $foreignTo = $subAccounts->createSubAccount((int) $otherMain->id, 'Foreign destination');

        $own = $this->scheduledSubAccountTransfer(
            (int) $ownFrom->id,
            (int) $ownTo->id,
            175,
            'active',
            'Own scheduled transfer',
            (int) $other->id,
        );

        $foreign = $this->scheduledSubAccountTransfer(
            (int) $foreignFrom->id,
            (int) $foreignTo->id,
            999,
            'active',
            'Foreign scheduled transfer',
            (int) $user->id,
        );

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/scheduled-operations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (int) $own->id)
            ->assertJsonPath('data.0.transaction_id', (int) $own->transaction_id)
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.amount_gol', 175)
            ->assertJsonPath('data.0.balance_bucket', 'active')
            ->assertJsonPath('data.0.description', 'Own scheduled transfer')
            ->assertJsonPath('data.0.source_sub_account.id', (int) $ownFrom->id)
            ->assertJsonPath('data.0.source_sub_account.code', (string) $ownFrom->sub_account_code)
            ->assertJsonPath('data.0.destination_sub_account.id', (int) $ownTo->id)
            ->assertJsonPath('data.0.destination_sub_account.code', (string) $ownTo->sub_account_code)
            ->assertJsonMissingPath('data.0.payload')
            ->assertJsonMissingPath('data.0.metadata')
            ->assertJsonMissingPath('data.0.actor_user_id')
            ->assertJsonMissingPath('data.0.account_id');

        $this->assertIsInt($response->json('data.0.amount_gol'));
        $this->assertNotSame((int) $foreign->id, (int) $response->json('data.0.id'));
    }

    public function test_m3_does_not_expose_schedule_creation_or_cancellation_without_mature_domain_policy(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $main = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Scheduled member');
        $subAccounts = app(SubAccountService::class);
        $from = $subAccounts->createSubAccount((int) $main->id, 'Source');
        $to = $subAccounts->createSubAccount((int) $main->id, 'Destination');
        $scheduled = $this->scheduledSubAccountTransfer(
            (int) $from->id,
            (int) $to->id,
            100,
            'active',
            'Existing scheduled transfer',
            (int) $user->id,
        );

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm3-schedule-create-not-supported-0001')
            ->postJson('/api/v1/najm-bahar/scheduled-operations', [
                'source_sub_account_id' => (int) $from->id,
                'destination_sub_account_code' => (string) $to->sub_account_code,
                'amount_gol' => 100,
                'execute_at' => now()->addDay()->toISOString(),
            ])
            ->assertStatus(405);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm3-schedule-cancel-not-supported-0001')
            ->deleteJson('/api/v1/najm-bahar/scheduled-operations/'.(int) $scheduled->id)
            ->assertNotFound();

        $this->assertSame('scheduled', (string) $scheduled->fresh()->status);
        $this->assertSame('pending', (string) NajmTransaction::findOrFail($scheduled->transaction_id)->status);
    }

    private function scheduledSubAccountTransfer(
        int $fromSubAccountId,
        int $toSubAccountId,
        int $amountGol,
        string $moneyState,
        string $description,
        int $actorUserId,
    ): ScheduledTransaction {
        $executeAt = now()->addDay();

        $placeholder = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => null,
            'amount' => $amountGol,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => $executeAt,
            'description' => $description,
            'metadata' => [
                'transfer_type' => 'subaccount',
                'from_sub_account_id' => $fromSubAccountId,
                'to_sub_account_id' => $toSubAccountId,
                'money_state' => $moneyState,
                'actor_user_id' => $actorUserId,
                'internal_secret' => 'must-not-leak',
            ],
        ]);

        return ScheduledTransaction::create([
            'transaction_id' => (int) $placeholder->id,
            'execute_at' => $executeAt,
            'status' => 'scheduled',
            'attempts' => 0,
            'payload' => [
                'type' => 'subaccount_transfer',
                'from_sub_account_id' => $fromSubAccountId,
                'to_sub_account_id' => $toSubAccountId,
                'amount' => $amountGol,
                'money_state' => $moneyState,
                'description' => $description,
                'metadata' => [
                    'actor_user_id' => $actorUserId,
                    'internal_secret' => 'must-not-leak',
                ],
            ],
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-scheduled-'.bin2hex(random_bytes(4)).'@example.test',
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
