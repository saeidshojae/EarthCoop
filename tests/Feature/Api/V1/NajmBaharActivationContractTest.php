<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Setting;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharActivationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligibility_is_server_derived_from_participation_policy_points_and_dim_balance(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.source', 'participation')
            ->assertJsonPath('data.remaining_convertible_points', 350)
            ->assertJsonPath('data.conversion_ratio_points_per_gol', 100)
            ->assertJsonPath('data.max_convertible_points', 300)
            ->assertJsonPath('data.max_activation_gol', 3)
            ->assertJsonPath('data.dim_available_gol', 10)
            ->assertJsonPath('data.active_gol', 5)
            ->assertJsonPath('data.policy_version', 1)
            ->assertJsonPath('data.policy_source', 'versioned_policy')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.account_id');

        foreach ([
            'data.remaining_convertible_points',
            'data.conversion_ratio_points_per_gol',
            'data.max_convertible_points',
            'data.max_activation_gol',
            'data.dim_available_gol',
            'data.active_gol',
        ] as $path) {
            $this->assertIsInt($response->json($path), $path.' must be an integer.');
        }

        $this->assertSame(15, (int) $account->fresh()->balance);
    }

    public function test_activation_requires_transport_idempotency_and_rejects_client_minted_authority_fields(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->accountFor($user, active: 0, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 500);

        $payload = [
            'source' => 'participation',
            'points' => 200,
        ];

        $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_required');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'activation-source-invalid-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'manual',
                'points' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'activation-authority-invalid-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                ...$payload,
                'amount_gol' => 999999,
                'reason' => 'client authority',
                'metadata' => ['system_operation' => true],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_activation_consumes_only_whole_ratio_points_and_moves_dim_to_active_without_minting(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $award = $this->awardConvertibleParticipationPoints($user, 250);
        $beforeTotal = (int) $account->balance;

        $response = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'activation-success-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 250,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'participation')
            ->assertJsonPath('data.requested_points', 250)
            ->assertJsonPath('data.consumed_points', 200)
            ->assertJsonPath('data.activated_gol', 2)
            ->assertJsonPath('data.balance.local.active_gol', 7)
            ->assertJsonPath('data.balance.local.dim_available_gol', 8)
            ->assertJsonPath('data.balance.local.total_gol', $beforeTotal)
            ->assertJsonMissingPath('data.metadata')
            ->assertJsonMissingPath('data.reason');

        $this->assertIsInt($response->json('data.activated_gol'));
        $this->assertSame($beforeTotal, (int) $account->fresh()->balance);
        $this->assertSame(7, (int) $account->fresh()->balance_active);
        $this->assertSame(8, (int) $account->fresh()->balance_faded);

        $conversionId = DB::table('user_point_conversions')
            ->where('user_id', $user->id)
            ->where('status', 'applied')
            ->value('id');

        $this->assertNotNull($conversionId);
        $this->assertDatabaseHas('user_point_conversions', [
            'id' => $conversionId,
            'user_id' => $user->id,
            'requested_points' => 250,
            'consumed_points' => 200,
            'amount_gol' => 2,
            'ratio' => 100,
            'status' => 'applied',
        ]);
        $this->assertSame(
            200,
            (int) DB::table('user_point_consumptions')
                ->where('user_point_conversion_id', $conversionId)
                ->sum('points_consumed')
        );
        $this->assertDatabaseHas('user_point_consumptions', [
            'user_point_conversion_id' => $conversionId,
            'user_point_transaction_id' => $award->id,
        ]);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()
            ->assertJsonPath('data.remaining_convertible_points', 50)
            ->assertJsonPath('data.max_convertible_points', 0)
            ->assertJsonPath('data.max_activation_gol', 0);
    }

    public function test_activation_fails_without_eligible_points_or_sufficient_dim_and_rolls_back_consumption(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 0, dim: 1);
        $this->enableParticipationConversion(ratio: 100);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'activation-no-points-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 100,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'activation_not_eligible');

        $this->awardConvertibleParticipationPoints($user, 300);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'activation-insufficient-dim-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 200,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'insufficient_dim');

        $this->assertSame(0, DB::table('user_point_consumptions')->count());
        $this->assertSame(0, (int) $account->fresh()->balance_active);
        $this->assertSame(1, (int) $account->fresh()->balance_faded);
        $this->assertSame(1, (int) $account->fresh()->balance);
    }

    public function test_activation_replay_is_single_effect_and_same_key_different_payload_conflicts(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 0, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 500);

        $headers = ['Idempotency-Key' => 'activation-replay-0001'];
        $payload = [
            'source' => 'participation',
            'points' => 200,
        ];

        $first = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertCreated();

        $conversionId = DB::table('user_point_conversions')->where('user_id', $user->id)->value('id');
        $transactionId = $first->json('data.transaction.id');

        $second = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.transaction.id', $transactionId);

        $this->assertSame(1, DB::table('user_point_conversions')->where('user_id', $user->id)->count());
        $this->assertSame(200, (int) DB::table('user_point_consumptions')->where('user_point_conversion_id', $conversionId)->sum('points_consumed'));
        $this->assertSame(2, (int) $account->fresh()->balance_active);
        $this->assertSame(8, (int) $account->fresh()->balance_faded);

        $changed = $payload;
        $changed['points'] = 300;

        $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/activation', $changed)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertSame(2, (int) $account->fresh()->balance_active);
        $this->assertSame(8, (int) $account->fresh()->balance_faded);
    }

    public function test_disabled_conversion_policy_fails_closed(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->accountFor($user, active: 0, dim: 10);
        $this->enableParticipationConversion(ratio: 100, enabled: false);
        $this->awardConvertibleParticipationPoints($user, 500);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'activation_disabled');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'activation-disabled-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 100,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'activation_disabled');
    }


    public function test_eligibility_v1_exposes_policy_identity_without_writing_versioned_state(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->accountFor($user, active: 5, dim: 10);
        $policy = $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);

        $beforePolicy = $policy->fresh()->toArray();
        $beforeConversions = DB::table('user_point_conversions')->count();
        $beforeConsumptions = DB::table('user_point_consumptions')->count();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()
            ->assertJsonPath('data.activation_contract_version', 1)
            ->assertJsonPath('data.policy_version_id', $policy->id)
            ->assertJsonPath('data.policy_version', 1)
            ->assertJsonPath('data.policy_source', 'versioned_policy');

        $this->assertSame($beforePolicy, $policy->fresh()->toArray());
        $this->assertSame($beforeConversions, DB::table('user_point_conversions')->count());
        $this->assertSame($beforeConsumptions, DB::table('user_point_consumptions')->count());
    }

    public function test_legacy_eligibility_v1_is_zero_write_and_does_not_migrate_setting_amounts(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->accountFor($user, active: 1, dim: 5);
        $this->awardConvertibleParticipationPoints($user, 250);

        MonetaryPolicyVersion::query()->delete();

        $setting = Setting::singleton();
        $setting->forceFill([
            'reputation_conversion_enabled' => true,
            'reputation_to_gol_ratio' => 100,
            'najm_bahar_amounts_in_gol' => false,
            'najm_bahar_initial_amount' => 10000,
            'najm_bahar_membership_fee_amount' => 12,
            'najm_bahar_membership_fee_membership_amount' => 6,
            'najm_bahar_membership_fee_insurance_amount' => 3,
            'najm_bahar_membership_fee_burn_amount' => 3,
        ])->save();

        $before = Setting::query()->findOrFail($setting->id)->getAttributes();
        $beforeUpdatedAt = (string) $setting->fresh()->updated_at;

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()
            ->assertJsonPath('data.activation_contract_version', 1)
            ->assertJsonPath('data.policy_version_id', null)
            ->assertJsonPath('data.policy_version', null)
            ->assertJsonPath('data.policy_source', 'legacy_settings')
            ->assertJsonPath('data.conversion_ratio_points_per_gol', 100)
            ->assertJsonPath('data.max_activation_gol', 2);

        $afterModel = Setting::query()->findOrFail($setting->id);
        $this->assertSame($before, $afterModel->getAttributes());
        $this->assertSame($beforeUpdatedAt, (string) $afterModel->updated_at);
        $this->assertFalse((bool) $afterModel->najm_bahar_amounts_in_gol);
        $this->assertSame(12, (int) $afterModel->najm_bahar_membership_fee_amount);
        $this->assertSame(6, (int) $afterModel->najm_bahar_membership_fee_membership_amount);
        $this->assertSame(3, (int) $afterModel->najm_bahar_membership_fee_insurance_amount);
        $this->assertSame(3, (int) $afterModel->najm_bahar_membership_fee_burn_amount);
    }

    private function accountFor(User $user, int $active, int $dim): Account
    {
        $account = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member '.$user->id);
        $account->balance_active = $active;
        $account->balance_faded = $dim;
        $account->committed_dim = 0;
        $account->balance = $active + $dim;
        $account->save();

        return $account->fresh();
    }

    private function awardConvertibleParticipationPoints(User $user, int $points): UserPointTransaction
    {
        return UserPointTransaction::create([
            'user_id' => $user->id,
            'delta' => $points,
            'balance_after' => $points,
            'action' => 'm3_activation_test_award',
            'dimension' => 'participation',
            'convertible' => true,
            'source' => 'm3_activation_contract',
        ]);
    }

    private function enableParticipationConversion(int $ratio, bool $enabled = true): MonetaryPolicyVersion
    {
        return MonetaryPolicyVersion::create([
            'version' => 1,
            'status' => 'active',
            'parameters' => [
                'reputation_conversion_enabled' => $enabled,
                'reputation_to_gol_ratio' => $ratio,
            ],
            'reason' => 'M3 activation contract',
            'effective_from' => now()->subMinute(),
            'approved_at' => now(),
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-activation-'.bin2hex(random_bytes(4)).'@example.test',
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
