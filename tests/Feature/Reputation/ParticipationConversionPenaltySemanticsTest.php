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

class ParticipationConversionPenaltySemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_participation_penalty_does_not_reduce_convertible_participation_entitlement(): void
    {
        [$user] = $this->member();

        $this->record($user->id, 500, 'participation', true, 'earned-participation');
        $this->record($user->id, -100, 'civic_trust', false, 'trust-penalty');

        $this->actingAs($user)
            ->getJson(route('reputation.conversion.info'))
            ->assertOk()
            ->assertJsonPath('total_points', 400)
            ->assertJsonPath('uncashed_points', 500);
    }

    public function test_explicit_convertible_participation_reversal_reduces_conversion_capacity(): void
    {
        [$user, $account] = $this->member();

        $this->record($user->id, 500, 'participation', true, 'earned-participation');
        $this->record($user->id, -100, 'participation', true, 'reversal-participation');

        $this->actingAs($user)
            ->getJson(route('reputation.conversion.info'))
            ->assertOk()
            ->assertJsonPath('total_points', 400)
            ->assertJsonPath('uncashed_points', 400);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'penalty-semantics-too-much')
            ->post(route('reputation.conversion.convert'), ['points' => 500])
            ->assertSessionHas('error');

        $this->assertSame(0, (int) $account->fresh()->balance_active);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'penalty-semantics-valid')
            ->post(route('reputation.conversion.convert'), ['points' => 400])
            ->assertRedirect(route('najm-bahar.wallet'));

        $this->assertSame(4, (int) $account->fresh()->balance_active);
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

    private function record(int $userId, int $delta, string $dimension, bool $convertible, string $suffix): UserPointTransaction
    {
        DB::table('user_points')->where('user_id', $userId)->increment('points', $delta);
        $balance = (int) DB::table('user_points')->where('user_id', $userId)->value('points');

        return UserPointTransaction::create([
            'user_id' => $userId,
            'delta' => $delta,
            'balance_after' => $balance,
            'action' => 'penalty_semantics_' . $suffix,
            'dimension' => $dimension,
            'convertible' => $convertible,
            'source' => 'penalty-semantics-test',
            'event_key' => 'penalty-semantics:' . $userId . ':' . $suffix,
        ]);
    }
}
