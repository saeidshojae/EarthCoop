<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharMembershipFeeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_info_uses_configured_integer_gol_fee_and_split_without_mutation(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->memberAccount($user);
        $this->policy(1_234, 600, 300, 334);
        $before = (int) $account->balance;

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/membership-fee')
            ->assertOk()
            ->assertJsonPath('data.has_paid', false)
            ->assertJsonPath('data.fee_gol', 1_234)
            ->assertJsonPath('data.breakdown.operations_salary_gol', 600)
            ->assertJsonPath('data.breakdown.central_insurance_gol', 300)
            ->assertJsonPath('data.breakdown.money_destruction_gol', 334)
            ->assertJsonPath('data.can_pay_from_dim', true)
            ->assertJsonPath('data.default_payment_source', 'dim')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.account_id');

        $this->assertIsInt($response->json('data.fee_gol'));
        $this->assertSame($before, (int) $account->fresh()->balance);
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee')->count());
    }

    public function test_payment_requires_idempotency_and_only_accepts_supported_sources(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->memberAccount($user);
        $this->policy();

        $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', ['payment_source' => 'dim'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_required');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'membership-source-invalid-0001')
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', ['payment_source' => 'manual'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_member_can_pay_from_dim_through_exact_activation_and_canonical_split(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->memberAccount($user);
        $this->policy(1_200, 600, 300, 300);
        $before = (int) $account->balance;

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'membership-dim-0001')
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', ['payment_source' => 'dim'])
            ->assertCreated()
            ->assertJsonPath('data.has_paid', true)
            ->assertJsonPath('data.fee_gol', 1_200)
            ->assertJsonPath('data.payment_source', 'dim')
            ->assertJsonPath('data.balance.local.active_gol', 0);

        $account->refresh();
        $this->assertSame($before - 1_200, (int) $account->balance);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame($before - 1_200, (int) $account->balance_faded);
        $this->assertSame(1, Transaction::where('metadata->type', 'membership_fee_activation')->count());
        $this->assertSame(3, Transaction::where('metadata->type', 'membership_fee')->count());

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/membership-fee')
            ->assertOk()
            ->assertJsonPath('data.has_paid', true);
    }

    public function test_active_payment_uses_existing_active_without_activating_dim(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->memberAccount($user);
        $this->policy(1_200, 600, 300, 300);
        app(MonetaryService::class)->activateDim(
            $account,
            1_200,
            'test preload',
            ['type' => 'test_preload'],
            'membership-api-preload-'.$user->id,
            false,
        );
        $beforeDim = (int) $account->fresh()->balance_faded;

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'membership-active-0001')
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', ['payment_source' => 'active'])
            ->assertCreated()
            ->assertJsonPath('data.payment_source', 'active')
            ->assertJsonPath('data.has_paid', true);

        $account->refresh();
        $this->assertSame($beforeDim, (int) $account->balance_faded);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee_activation')->count());
        $this->assertSame(3, Transaction::where('metadata->type', 'membership_fee')->count());
    }

    public function test_active_payment_fails_closed_when_available_active_is_insufficient(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->memberAccount($user);
        $this->policy(1_200, 600, 300, 300);
        $before = (int) $account->balance;

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'membership-insufficient-0001')
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', ['payment_source' => 'active'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'insufficient_available_funds');

        $this->assertSame($before, (int) $account->fresh()->balance);
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee')->count());
    }

    public function test_explicit_subaccount_source_must_belong_to_authenticated_member(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->memberAccount($user);
        $other = User::factory()->create(['is_system' => false]);
        $otherMain = $this->memberAccount($other);
        $this->policy(1_200, 600, 300, 300);

        $foreign = SubAccount::create([
            'account_id' => $otherMain->id,
            'sub_account_code' => $otherMain->account_number.'-991',
            'name' => 'Foreign source',
            'balance' => 2_000,
            'balance_active' => 2_000,
            'balance_faded' => 0,
            'status' => 1,
        ]);
        app(AccountService::class)->ensureSubAccountAccount($foreign);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'membership-foreign-sub-0001')
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', [
                'payment_source' => 'active',
                'sub_account_id' => $foreign->id,
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->assertSame(2_000, (int) $foreign->fresh()->balance_active);
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee')->count());
    }

    public function test_payment_replay_is_single_effect_and_changed_payload_conflicts(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->memberAccount($user);
        $this->policy(1_200, 600, 300, 300);
        $headers = ['Idempotency-Key' => 'membership-replay-0001'];
        $payload = ['payment_source' => 'dim'];

        $first = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', $payload)
            ->assertCreated();
        $balanceAfter = (int) $account->fresh()->balance;

        $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.payment_year', $first->json('data.payment_year'));

        $this->assertSame($balanceAfter, (int) $account->fresh()->balance);
        $this->assertSame(3, Transaction::where('metadata->type', 'membership_fee')->count());

        $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', ['payment_source' => 'active'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertSame($balanceAfter, (int) $account->fresh()->balance);
    }

    private function memberAccount(User $user)
    {
        $account = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member '.$user->id);
        app(MonetaryService::class)->issueMembershipCredit($account, (int) $user->id);

        return $account->fresh();
    }

    private function policy(int $fee = 1_200, int $operations = 600, int $insurance = 300, int $burn = 300): void
    {
        MonetaryPolicyVersion::create([
            'version' => 901,
            'status' => 'active',
            'effective_from' => now()->subMinute(),
            'approved_at' => now(),
            'parameters' => [
                'membership_fee_gol' => $fee,
                'membership_operations_gol' => $operations,
                'membership_insurance_gol' => $insurance,
                'membership_burn_gol' => $burn,
            ],
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-membership-'.bin2hex(random_bytes(4)).'@example.test',
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
