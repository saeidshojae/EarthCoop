<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ReputationController;
use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReputationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_up_to_daily_cap(): void
    {
        $user = User::factory()->create();
        config([
            'reputation.weights.test_action' => 2,
            'reputation.daily_caps.test_action' => 5,
        ]);

        $service = app(ReputationService::class);

        for ($i = 0; $i < 3; $i++) {
            $service->applyAction($user, 'test_action', [], null, 'unit.test');
        }

        $sum = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'test_action')
            ->where('delta', '>', 0)
            ->sum('delta');

        $this->assertSame(5, (int) $sum);
    }

    public function test_no_award_when_cap_exhausted(): void
    {
        $user = User::factory()->create();
        config([
            'reputation.weights.only_one' => 1,
            'reputation.daily_caps.only_one' => 1,
        ]);

        $service = app(ReputationService::class);

        $service->applyAction($user, 'only_one', [], null, 'unit.test');
        $service->applyAction($user, 'only_one', [], null, 'unit.test');

        $sum = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'only_one')
            ->where('delta', '>', 0)
            ->sum('delta');

        $this->assertSame(1, (int) $sum);
    }

    public function test_database_daily_cap_is_authoritative_when_rule_exists(): void
    {
        $user = User::factory()->create();
        config([
            'reputation.weights.db_capped_action' => 99,
            'reputation.daily_caps.db_capped_action' => 99,
        ]);

        ReputationRule::create([
            'key' => 'db_capped_action',
            'label' => 'DB capped action',
            'weight' => 2,
            'daily_cap' => 5,
            'active' => true,
        ]);

        $service = app(ReputationService::class);
        for ($i = 0; $i < 3; $i++) {
            $service->applyAction($user, 'db_capped_action', [], null, 'unit.test');
        }

        $sum = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'db_capped_action')
            ->where('delta', '>', 0)
            ->sum('delta');

        $this->assertSame(5, (int) $sum, 'Runtime must honor the daily cap saved in reputation_rules.');
    }

    public function test_inactive_database_rule_does_not_fall_back_to_config(): void
    {
        $user = User::factory()->create();
        config(['reputation.weights.disabled_action' => 50]);

        ReputationRule::create([
            'key' => 'disabled_action',
            'label' => 'Disabled action',
            'weight' => 50,
            'daily_cap' => null,
            'active' => false,
        ]);

        $result = app(ReputationService::class)->applyAction(
            $user,
            'disabled_action',
            [],
            null,
            'unit.test'
        );

        $this->assertNull($result);
        $this->assertSame(
            0,
            UserPointTransaction::where('user_id', $user->id)
                ->where('action', 'disabled_action')
                ->count(),
            'An explicitly disabled DB rule must remain disabled even when config has a fallback weight.'
        );
    }

    public function test_opening_admin_reputation_page_does_not_overwrite_saved_rule_values(): void
    {
        config([
            'reputation.weights.post_created' => 10,
            'reputation.daily_caps.post_created' => 40,
        ]);

        $rule = ReputationRule::create([
            'key' => 'post_created',
            'label' => 'Post created',
            'weight' => 77,
            'daily_cap' => 3,
            'active' => false,
            'description' => 'Admin-authored policy',
        ]);

        app(ReputationController::class)->index();

        $rule->refresh();
        $this->assertSame(77, (int) $rule->weight);
        $this->assertSame(3, (int) $rule->daily_cap);
        $this->assertFalse((bool) $rule->active);
        $this->assertSame('Admin-authored policy', $rule->description);
    }
}
