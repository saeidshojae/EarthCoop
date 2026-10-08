<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
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

    public function test_financial_idempotency_schema_has_database_unique_indexes(): void
    {
        // The protection must reside in MySQL, not only application-side checks.
        $conversionIndexes = DB::select('SHOW INDEX FROM user_point_conversions');
        $conversionGroups = collect($conversionIndexes)->groupBy('Key_name');
        $this->assertTrue($conversionGroups->has('user_point_conversions_user_request_unique'));
        $conversionColumns = $conversionGroups
            ->get('user_point_conversions_user_request_unique')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->all();
        $this->assertSame(['user_id', 'request_key'], $conversionColumns);
        $this->assertTrue($conversionGroups
            ->contains(fn ($rows) => $rows->count() === 1
                && $rows->first()->Column_name === 'conversion_key'
                && (int) $rows->first()->Non_unique === 0));

        $transportIndexes = collect(DB::select('SHOW INDEX FROM api_v1_idempotency_keys'))
            ->groupBy('Key_name');
        $this->assertTrue($transportIndexes->has('api_v1_idem_actor_scope_key_unique'));
        $this->assertSame(
            ['actor_key', 'scope', 'idempotency_key'],
            $transportIndexes->get('api_v1_idem_actor_scope_key_unique')
                ->sortBy('Seq_in_index')
                ->pluck('Column_name')
                ->all(),
        );
        $this->assertTrue($transportIndexes->get('api_v1_idem_actor_scope_key_unique')
            ->every(fn ($row) => (int) $row->Non_unique === 0));
    }

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
            ->assertJsonPath('data.activation_contract_version', 1)
            ->assertJsonPath('data.policy_version_id', 1)
            ->assertJsonPath('data.max_activation_points', 300)
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

        $this->assertSame(0, DB::table('user_point_conversions')->count());
        $this->assertSame(0, DB::table('user_point_consumptions')->count());
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

    public function test_native_strict_activation_requires_exact_points_without_legacy_flooring(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);

        $expected = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()->json('data');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-activation-nonmultiple-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 250,
                'expected' => [
                    'activation_contract_version' => $expected['activation_contract_version'],
                    'policy_version_id' => $expected['policy_version_id'],
                    'policy_version' => $expected['policy_version'],
                    'conversion_ratio_points_per_gol' => $expected['conversion_ratio_points_per_gol'],
                    'remaining_convertible_points' => $expected['remaining_convertible_points'],
                    'dim_available_gol' => $expected['dim_available_gol'],
                    'max_activation_gol' => $expected['max_activation_gol'],
                ],
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'activation_not_eligible');

        $this->assertSame(0, DB::table('user_point_conversions')->count());
        $this->assertSame(0, DB::table('user_point_consumptions')->count());
        $this->assertSame(5, (int) $account->fresh()->balance_active);
        $this->assertSame(10, (int) $account->fresh()->balance_faded);
    }

    public function test_native_activation_rejects_changed_policy_snapshot_without_money_or_point_mutation(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 0, dim: 10);
        $policy = $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);

        $expected = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()->json('data');

        $policy->update(['parameters' => [
            'reputation_conversion_enabled' => true,
            'reputation_to_gol_ratio' => 50,
        ]]);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-activation-stale-policy-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 200,
                'expected' => [
                    'activation_contract_version' => $expected['activation_contract_version'],
                    'policy_version_id' => $expected['policy_version_id'],
                    'policy_version' => $expected['policy_version'],
                    'conversion_ratio_points_per_gol' => $expected['conversion_ratio_points_per_gol'],
                    'remaining_convertible_points' => $expected['remaining_convertible_points'],
                    'dim_available_gol' => $expected['dim_available_gol'],
                    'max_activation_gol' => $expected['max_activation_gol'],
                ],
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'activation_terms_changed');

        $this->assertSame(0, DB::table('user_point_conversions')->count());
        $this->assertSame(0, DB::table('user_point_consumptions')->count());
        $this->assertSame(0, (int) $account->fresh()->balance_active);
        $this->assertSame(10, (int) $account->fresh()->balance_faded);
    }

    public function test_native_activation_rejects_changed_points_and_dim_snapshots_without_mutation(): void
    {
        foreach (['points', 'dim'] as $index => $changed) {
            [$user, $token, $deviceId] = $this->nativeSession();
            $account = $this->accountFor($user, active: 0, dim: 10);
            $this->enableParticipationConversion(ratio: 100, version: $index + 1);
            $this->awardConvertibleParticipationPoints($user, 350);

            $expected = $this->bearer($token, $deviceId)
                ->getJson('/api/v1/najm-bahar/activation/eligibility')
                ->assertOk()->json('data');

            if ($changed === 'points') {
                $this->awardConvertibleParticipationPoints($user, 100);
            } else {
                $account->balance_faded = 9;
                $account->balance = 9;
                $account->save();
            }

            $this->bearer($token, $deviceId)
                ->withHeader('Idempotency-Key', 'native-stale-'.$changed.'-0001')
                ->postJson('/api/v1/najm-bahar/activation', [
                    'source' => 'participation',
                    'points' => 200,
                    'expected' => [
                        'activation_contract_version' => $expected['activation_contract_version'],
                        'policy_version_id' => $expected['policy_version_id'],
                        'policy_version' => $expected['policy_version'],
                        'conversion_ratio_points_per_gol' => $expected['conversion_ratio_points_per_gol'],
                        'remaining_convertible_points' => $expected['remaining_convertible_points'],
                        'dim_available_gol' => $expected['dim_available_gol'],
                        'max_activation_gol' => $expected['max_activation_gol'],
                    ],
                ])->assertStatus(409)
                ->assertJsonPath('error.code', 'activation_terms_changed');

            $this->assertSame(0, DB::table('user_point_conversions')->where('user_id', $user->id)->count());
            $this->assertSame(0, DB::table('user_point_consumptions')->where('user_id', $user->id)->count());
            $this->assertSame(0, (int) $account->fresh()->balance_active);
            $this->assertSame($changed === 'dim' ? 9 : 10, (int) $account->fresh()->balance_faded);
        }
    }

    public function test_native_exact_activation_success_replay_is_single_effect(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);

        $eligibility = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()->json('data');
        $payload = [
            'source' => 'participation',
            'points' => 200,
            'expected' => [
                'activation_contract_version' => $eligibility['activation_contract_version'],
                'policy_version_id' => $eligibility['policy_version_id'],
                'policy_version' => $eligibility['policy_version'],
                'conversion_ratio_points_per_gol' => $eligibility['conversion_ratio_points_per_gol'],
                'remaining_convertible_points' => $eligibility['remaining_convertible_points'],
                'dim_available_gol' => $eligibility['dim_available_gol'],
                'max_activation_gol' => $eligibility['max_activation_gol'],
            ],
        ];
        $headers = ['Idempotency-Key' => 'native-activation-exact-replay-0001'];

        $first = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertCreated()
            ->assertJsonPath('data.requested_points', 200)
            ->assertJsonPath('data.consumed_points', 200)
            ->assertJsonPath('data.activated_gol', 2)
            ->assertJsonPath('data.balance.local.active_gol', 7)
            ->assertJsonPath('data.balance.local.dim_available_gol', 8);

        $second = $this->bearer($token, $deviceId)
            ->withHeaders($headers)
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.transaction.id', $first->json('data.transaction.id'));

        $this->assertSame(1, DB::table('user_point_conversions')->where('user_id', $user->id)->count());
        $this->assertSame(200, (int) DB::table('user_point_consumptions')->where('user_id', $user->id)->sum('points_consumed'));
        $this->assertSame(7, (int) $account->fresh()->balance_active);
        $this->assertSame(8, (int) $account->fresh()->balance_faded);
        $this->assertSame(15, (int) $account->fresh()->balance);
    }

    public function test_distinct_native_keys_cannot_reuse_the_same_reviewed_points_snapshot(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 300);

        $eligibility = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()->json('data');
        $expected = array_intersect_key($eligibility, array_flip([
            'activation_contract_version',
            'policy_version_id',
            'policy_version',
            'conversion_ratio_points_per_gol',
            'remaining_convertible_points',
            'dim_available_gol',
            'max_activation_gol',
        ]));
        $payload = [
            'source' => 'participation',
            'points' => 200,
            'expected' => $expected,
        ];

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-cross-key-first-0001')
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertCreated()->assertJsonPath('data.activated_gol', 2);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-cross-key-second-0001')
            ->postJson('/api/v1/najm-bahar/activation', $payload)
            ->assertStatus(409)->assertJsonPath('error.code', 'activation_terms_changed');

        $this->assertSame(1, DB::table('user_point_conversions')
            ->where('user_id', $user->id)->count());
        $this->assertSame(200, (int) DB::table('user_point_consumptions')
            ->where('user_id', $user->id)->sum('points_consumed'));
        $this->assertSame(7, (int) $account->fresh()->balance_active);
        $this->assertSame(8, (int) $account->fresh()->balance_faded);
        $this->assertSame(15, (int) $account->fresh()->balance);
    }

    public function test_reversal_after_successful_activation_must_not_overconsume_earned_points(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 300);

        $eligible = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()->json('data');
        $expected = array_intersect_key($eligible, array_flip([
            'activation_contract_version', 'policy_version_id', 'policy_version',
            'conversion_ratio_points_per_gol', 'remaining_convertible_points',
            'dim_available_gol', 'max_activation_gol',
        ]));
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'crosswriter-first-activation-0001')
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 200,
                'expected' => $expected,
            ])->assertCreated();

        // A 200-point reversal after 200 points have been consumed from 300
        // must not silently create a negative participation entitlement.
        app(\\App\\Services\\ReputationService::class)->addPoints(
            $user, -200, 'crosswriter_reverse_after_activation', [], null,
            'activation_contract', 'participation', true,
            'crosswriter-reversal-after-activation-0001'
        );

        $consumed = (int) DB::table('user_point_consumptions')
            ->where('user_id', $user->id)->sum('points_consumed');
        $reversed = abs((int) DB::table('user_point_transactions')
            ->where('user_id', $user->id)->where('delta', '<', 0)
            ->where('convertible', true)->where('dimension', 'participation')
            ->sum('delta'));
        $this->assertLessThanOrEqual(300, $consumed + $reversed);
    }

    public function test_activation_reconciliation_is_read_only_and_scoped_to_successful_owned_intent(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $account = $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);

        $url = '/api/v1/najm-bahar/activation/by-idempotency/native-reconcile-success-0001';
        $this->bearer($token, $deviceId)->getJson($url)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');

        $expected = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')->assertOk()->json('data');
        $payload = [
            'source' => 'participation',
            'points' => 200,
            'expected' => [
                'activation_contract_version' => $expected['activation_contract_version'],
                'policy_version_id' => $expected['policy_version_id'],
                'policy_version' => $expected['policy_version'],
                'conversion_ratio_points_per_gol' => $expected['conversion_ratio_points_per_gol'],
                'remaining_convertible_points' => $expected['remaining_convertible_points'],
                'dim_available_gol' => $expected['dim_available_gol'],
                'max_activation_gol' => $expected['max_activation_gol'],
            ],
        ];
        $stored = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'native-reconcile-success-0001')
            ->postJson('/api/v1/najm-bahar/activation', $payload)->assertCreated();

        $before = DB::table('user_point_consumptions')->where('user_id', $user->id)->sum('points_consumed');
        $this->bearer($token, $deviceId)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.transaction.id', $stored->json('data.transaction.id'))
            ->assertJsonPath('data.activated_gol', 2)
            ->assertJsonPath('data.consumed_points', 200);

        $this->assertSame((int) $before, (int) DB::table('user_point_consumptions')->where('user_id', $user->id)->sum('points_consumed'));
        $this->assertSame(7, (int) $account->fresh()->balance_active);
        $this->assertSame(8, (int) $account->fresh()->balance_faded);
    }

    public function test_activation_reconciliation_rejects_tampered_receipt_and_missing_ledger(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();
        $this->accountFor($user, active: 5, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($user, 350);
        $key = 'native-reconcile-tamper-0001';
        $expected = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')->assertOk()->json('data');
        $this->bearer($token, $deviceId)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation', 'points' => 200,
                'expected' => array_intersect_key($expected, array_flip([
                    'activation_contract_version', 'policy_version_id', 'policy_version',
                    'conversion_ratio_points_per_gol', 'remaining_convertible_points',
                    'dim_available_gol', 'max_activation_gol',
                ])),
            ])->assertCreated();

        $row = DB::table('api_v1_idempotency_keys')
            ->where('actor_key', 'user:'.$user->id)
            ->where('scope', 'api.v1.najm-bahar.activation.store')
            ->where('idempotency_key', $key)->first();
        $this->assertNotNull($row);
        $original = (string) $row->response_body;
        $body = json_decode($original, true);
        $body['data']['activated_gol'] = '2';
        DB::table('api_v1_idempotency_keys')->where('id', $row->id)
            ->update(['response_body' => json_encode($body)]);
        $url = '/api/v1/najm-bahar/activation/by-idempotency/'.$key;
        $this->bearer($token, $deviceId)->getJson($url)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');

        $body = json_decode($original, true);
        $body['data']['transaction']['id'] = 999999999;
        DB::table('api_v1_idempotency_keys')->where('id', $row->id)
            ->update(['response_body' => json_encode($body)]);
        $this->bearer($token, $deviceId)->getJson($url)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_activation_reconciliation_rejects_foreign_user_and_wrong_route_scope(): void
    {
        [$owner, $ownerToken, $ownerDevice] = $this->nativeSession();
        $this->accountFor($owner, active: 0, dim: 10);
        $this->enableParticipationConversion(ratio: 100);
        $this->awardConvertibleParticipationPoints($owner, 300);
        $key = 'native-reconcile-scope-0001';
        $expected = $this->bearer($ownerToken, $ownerDevice)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')->assertOk()->json('data');

        $this->bearer($ownerToken, $ownerDevice)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/najm-bahar/activation', [
                'source' => 'participation',
                'points' => 200,
                'expected' => array_intersect_key($expected, array_flip([
                    'activation_contract_version', 'policy_version_id', 'policy_version',
                    'conversion_ratio_points_per_gol', 'remaining_convertible_points',
                    'dim_available_gol', 'max_activation_gol',
                ])),
            ])->assertCreated();

        $url = '/api/v1/najm-bahar/activation/by-idempotency/'.$key;
        [$other, $otherToken, $otherDevice] = $this->nativeSession();
        $this->bearer($otherToken, $otherDevice)->getJson($url)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');

        DB::table('api_v1_idempotency_keys')
            ->where('actor_key', 'user:'.$owner->id)
            ->where('idempotency_key', $key)
            ->update(['scope' => 'api.v1.najm-bahar.transfers.store']);
        $this->bearer($ownerToken, $ownerDevice)->getJson($url)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');
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

    private function enableParticipationConversion(int $ratio, bool $enabled = true, int $version = 1): MonetaryPolicyVersion
    {
        return MonetaryPolicyVersion::create([
            'version' => $version,
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
