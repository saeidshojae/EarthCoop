<?php

namespace Tests\Feature\Reputation;

use App\Models\User;
use App\Models\UserPointConsumption;
use App\Models\UserPointTransaction;
use App\Services\ParticipationPointSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipationPointSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_total_points_from_economic_conversion_capacity(): void
    {
        $user = User::factory()->create();
        DB::table('user_points')->insert([
            'user_id' => $user->id,
            'points' => 620,
            'level' => 'Silver',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eligible = $this->transaction($user->id, 100, 'participation', true, false, 'eligible');
        $this->transaction($user->id, 200, 'participation', false, false, 'nonconvertible');
        $this->transaction($user->id, 300, 'reliability', true, false, 'wrong-dimension');
        $this->transaction($user->id, -20, 'participation', true, false, 'economic-reversal');
        $this->transaction($user->id, 40, 'participation', true, true, 'legacy-cashed');

        DB::table('user_point_conversions')->insert([
            'user_id' => $user->id,
            'request_key' => 'summary-test',
            'conversion_key' => 'reputation-conversion:' . $user->id . ':summary-test',
            'requested_points' => 30,
            'consumed_points' => 30,
            'amount_gol' => 0,
            'ratio' => 100,
            'status' => 'applied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $conversionId = (int) DB::table('user_point_conversions')->where('request_key', 'summary-test')->value('id');

        UserPointConsumption::create([
            'user_id' => $user->id,
            'user_point_conversion_id' => $conversionId,
            'user_point_transaction_id' => $eligible->id,
            'points_consumed' => 30,
            'conversion_key' => 'reputation-conversion:' . $user->id . ':summary-test',
            'policy_version' => 1,
        ]);

        $summary = app(ParticipationPointSummaryService::class)->forUser($user->id);

        $this->assertSame(620, $summary['total_points']);
        $this->assertSame('Silver', $summary['level']);
        $this->assertSame(100, $summary['convertible_awarded_points']);
        $this->assertSame(30, $summary['ledger_consumed_points']);
        $this->assertSame(40, $summary['legacy_cashed_points']);
        $this->assertSame(20, $summary['participation_reversal_points']);
        $this->assertSame(70, $summary['cashed_points']);
        $this->assertSame(50, $summary['remaining_convertible_points']);
        $this->assertSame(50, $summary['uncashed_points']);
    }

    private function transaction(int $userId, int $delta, string $dimension, bool $convertible, bool $isCashed, string $suffix): UserPointTransaction
    {
        $id = DB::table('user_point_transactions')->insertGetId([
            'user_id' => $userId,
            'delta' => $delta,
            'balance_after' => 0,
            'action' => 'summary_' . $suffix,
            'dimension' => $dimension,
            'convertible' => $convertible,
            'is_cashed' => $isCashed,
            'source' => 'summary-test',
            'event_key' => 'summary-test:' . $userId . ':' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return UserPointTransaction::findOrFail($id);
    }
}
