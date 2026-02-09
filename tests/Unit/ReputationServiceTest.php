<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;

class ReputationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_up_to_daily_cap()
    {
        // arrange
        $user = User::factory()->create();
        config(['reputation.daily_caps.test_action' => 5]);
        // ensure rule not present so config path used

        $service = app(ReputationService::class);

        // act: call 3 times weight 2 (total 6) but cap is 5
        for ($i = 0; $i < 3; $i++) {
            $service->applyAction($user, 'test_action', [], null, 'unit.test');
        }

        // assert: total awarded in last 24h <= cap
        $sum = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'test_action')
            ->where('delta', '>', 0)
            ->sum('delta');

        $this->assertLessThanOrEqual(5, $sum);
    }

    public function test_no_award_when_cap_exhausted()
    {
        $user = User::factory()->create();
        config(['reputation.daily_caps.only_one' => 1]);

        $service = app(ReputationService::class);

        // first award
        $service->applyAction($user, 'only_one', [], null, 'unit.test');
        // second award should be skipped or capped to 0
        $service->applyAction($user, 'only_one', [], null, 'unit.test');

        $sum = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'only_one')
            ->where('delta', '>', 0)
            ->sum('delta');

        $this->assertLessThanOrEqual(1, $sum);
    }
}
