<?php

namespace Tests\Feature\Reputation;

use App\Models\User;
use App\Models\UserPointConsumption;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipationConversionBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_conversion_consumes_exact_points_and_preserves_remainder(): void
    {
        [$user, $account, $tx] = $this->memberWithConvertiblePoints(150);

        $this->actingAs($user)
            ->post(route('reputation.conversion.convert'), ['points' => 150])
            ->assertRedirect(route('najm-bahar.wallet'));

        $this->assertSame(100, (int) UserPointConsumption::where('user_point_transaction_id', $tx->id)->sum('points_consumed'));
        $this->assertSame(50, (int) $tx->delta - (int) UserPointConsumption::where('user_point_transaction_id', $tx->id)->sum('points_consumed'));

        $account->refresh();
        $this->assertSame(1, (int) $account->balance_active);
        $this->assertSame(1, Transaction::where('metadata->type', 'reputation_conversion')->count());
    }

    public function test_retry_with_same_conversion_request_key_cannot_consume_or_activate_twice(): void
    {
        [$user, $account, $tx] = $this->memberWithConvertiblePoints(100);
        $requestKey = 'fixed-retry-key';
        $conversionKey = 'reputation-conversion:' . $user->id . ':' . $requestKey;

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', $requestKey)
            ->post(route('reputation.conversion.convert'), ['points' => 100])
            ->assertRedirect(route('najm-bahar.wallet'));

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', $requestKey)
            ->post(route('reputation.conversion.convert'), ['points' => 100])
            ->assertRedirect(route('najm-bahar.wallet'));

        $this->assertSame(100, (int) UserPointConsumption::where('user_point_transaction_id', $tx->id)->sum('points_consumed'));
        $this->assertSame(1, UserPointConsumption::where('conversion_key', $conversionKey)->count());
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', $conversionKey)->count());
        $this->assertSame(1, (int) $account->fresh()->balance_active);
    }

    public function test_same_client_request_key_is_isolated_between_users(): void
    {
        [$firstUser, $firstAccount] = $this->memberWithConvertiblePoints(100);
        [$secondUser, $secondAccount] = $this->memberWithConvertiblePoints(100);
        $requestKey = 'shared-client-key';

        $this->actingAs($firstUser)
            ->withHeader('Idempotency-Key', $requestKey)
            ->post(route('reputation.conversion.convert'), ['points' => 100])
            ->assertRedirect(route('najm-bahar.wallet'));

        $this->actingAs($secondUser)
            ->withHeader('Idempotency-Key', $requestKey)
            ->post(route('reputation.conversion.convert'), ['points' => 100])
            ->assertRedirect(route('najm-bahar.wallet'));

        $firstKey = 'reputation-conversion:' . $firstUser->id . ':' . $requestKey;
        $secondKey = 'reputation-conversion:' . $secondUser->id . ':' . $requestKey;

        $this->assertNotSame($firstKey, $secondKey);
        $this->assertSame(1, UserPointConsumption::where('conversion_key', $firstKey)->count());
        $this->assertSame(1, UserPointConsumption::where('conversion_key', $secondKey)->count());
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', $firstKey)->count());
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', $secondKey)->count());
        $this->assertSame(1, (int) $firstAccount->fresh()->balance_active);
        $this->assertSame(1, (int) $secondAccount->fresh()->balance_active);
    }

    private function memberWithConvertiblePoints(int $points): array
    {
        if (! MonetaryPolicyVersion::where('status', 'active')->exists()) {
            MonetaryPolicyVersion::create([
                'version' => 1,
                'status' => 'active',
                'parameters' => [
                    'reputation_conversion_enabled' => true,
                    'reputation_to_gol_ratio' => 100,
                ],
                'effective_from' => now()->subMinute(),
            ]);
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($account, $user->id);

        DB::table('user_points')->insert([
            'user_id' => $user->id,
            'points' => $points,
            'level' => 'Bronze',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tx = UserPointTransaction::create([
            'user_id' => $user->id,
            'delta' => $points,
            'balance_after' => $points,
            'action' => 'behavior_test',
            'dimension' => 'participation',
            'convertible' => true,
            'source' => 'test',
            'event_key' => 'behavior-test:' . $user->id,
        ]);

        return [$user, $account->fresh(), $tx];
    }
}
