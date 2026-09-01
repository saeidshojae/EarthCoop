<?php

namespace Tests\Feature\Reputation;

use App\Models\User;
use App\Models\UserPointConsumption;
use App\Models\UserPointConversion;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipationConversionLegacyCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_cashed_points_are_reported_as_consumed_not_convertible(): void
    {
        [$user] = $this->memberWithLegacyCashedPoints(100);

        $this->actingAs($user)
            ->getJson(route('reputation.conversion.info'))
            ->assertOk()
            ->assertJsonPath('uncashed_points', 0)
            ->assertJsonPath('cashed_points', 100);
    }

    public function test_legacy_cashed_points_cannot_activate_dim_again(): void
    {
        [$user, $account, $tx] = $this->memberWithLegacyCashedPoints(100);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'legacy-reconversion-attempt')
            ->post(route('reputation.conversion.convert'), ['points' => 100])
            ->assertSessionHas('error');

        $this->assertSame(0, (int) $account->fresh()->balance_active);
        $this->assertSame(0, UserPointConsumption::where('user_point_transaction_id', $tx->id)->count());
        $this->assertSame(0, UserPointConversion::where('user_id', $user->id)->count());
    }

    private function memberWithLegacyCashedPoints(int $points): array
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

        $txId = DB::table('user_point_transactions')->insertGetId([
            'user_id' => $user->id,
            'delta' => $points,
            'balance_after' => $points,
            'action' => 'legacy_cashed_test',
            'dimension' => 'participation',
            'convertible' => true,
            'source' => 'legacy-test',
            'event_key' => 'legacy-cashed-test:' . $user->id,
            'is_cashed' => true,
            'cashed_at' => now(),
            'cashed_amount_gol' => intdiv($points, 100),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tx = UserPointTransaction::findOrFail($txId);

        return [$user, $account->fresh(), $tx];
    }
}
