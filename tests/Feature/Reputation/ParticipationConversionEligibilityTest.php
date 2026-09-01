<?php

namespace Tests\Feature\Reputation;

use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipationConversionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_convertible_participation_snapshot_points_are_exposed_for_conversion(): void
    {
        [$user] = $this->member();

        $this->award($user->id, 100, 'participation', true, 'eligible');
        $this->award($user->id, 200, 'participation', false, 'nonconvertible');
        $this->award($user->id, 300, 'reliability', true, 'wrong-dimension');

        $this->actingAs($user)
            ->getJson(route('reputation.conversion.info'))
            ->assertOk()
            ->assertJsonPath('total_points', 600)
            ->assertJsonPath('uncashed_points', 100)
            ->assertJsonPath('cashed_points', 0);
    }

    public function test_conversion_consumes_only_eligible_snapshot_points(): void
    {
        [$user, $account] = $this->member();

        $eligible = $this->award($user->id, 100, 'participation', true, 'eligible');
        $ineligible = $this->award($user->id, 100, 'participation', false, 'nonconvertible');
        $wrongDimension = $this->award($user->id, 100, 'expertise', true, 'wrong-dimension');

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'eligibility-conversion')
            ->post(route('reputation.conversion.convert'), ['points' => 100])
            ->assertRedirect(route('najm-bahar.wallet'));

        $this->assertSame(1, (int) $account->fresh()->balance_active);
        $this->assertSame(100, (int) $eligible->consumptions()->sum('points_consumed'));
        $this->assertSame(0, (int) $ineligible->consumptions()->sum('points_consumed'));
        $this->assertSame(0, (int) $wrongDimension->consumptions()->sum('points_consumed'));
    }

    private function member(): array
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
            'points' => 0,
            'level' => 'Bronze',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $account->fresh()];
    }

    private function award(int $userId, int $points, string $dimension, bool $convertible, string $suffix): UserPointTransaction
    {
        DB::table('user_points')->where('user_id', $userId)->increment('points', $points);
        $balance = (int) DB::table('user_points')->where('user_id', $userId)->value('points');

        return UserPointTransaction::create([
            'user_id' => $userId,
            'delta' => $points,
            'balance_after' => $balance,
            'action' => 'eligibility_test_' . $suffix,
            'dimension' => $dimension,
            'convertible' => $convertible,
            'source' => 'eligibility-test',
            'event_key' => 'eligibility-test:' . $userId . ':' . $suffix,
        ]);
    }
}
