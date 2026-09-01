<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ReputationController;
use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReputationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_up_to_daily_cap(): void
    {
        $user = User::factory()->create();
        config(['reputation.weights.test_action' => 2, 'reputation.daily_caps.test_action' => 5]);
        $service = app(ReputationService::class);
        for ($i = 0; $i < 3; $i++) $service->applyAction($user, 'test_action', [], null, 'unit.test');
        $this->assertSame(5, (int) UserPointTransaction::where('user_id', $user->id)->where('action', 'test_action')->where('delta', '>', 0)->sum('delta'));
    }

    public function test_no_award_when_cap_exhausted(): void
    {
        $user = User::factory()->create();
        config(['reputation.weights.only_one' => 1, 'reputation.daily_caps.only_one' => 1]);
        $service = app(ReputationService::class);
        $service->applyAction($user, 'only_one', [], null, 'unit.test');
        $service->applyAction($user, 'only_one', [], null, 'unit.test');
        $this->assertSame(1, (int) UserPointTransaction::where('user_id', $user->id)->where('action', 'only_one')->where('delta', '>', 0)->sum('delta'));
    }

    public function test_database_daily_cap_is_authoritative_when_rule_exists(): void
    {
        $user = User::factory()->create();
        config(['reputation.weights.db_capped_action' => 99, 'reputation.daily_caps.db_capped_action' => 99]);
        ReputationRule::create(['key'=>'db_capped_action','label'=>'DB capped action','weight'=>2,'daily_cap'=>5,'active'=>true]);
        $service = app(ReputationService::class);
        for ($i = 0; $i < 3; $i++) $service->applyAction($user, 'db_capped_action', [], null, 'unit.test');
        $this->assertSame(5, (int) UserPointTransaction::where('user_id',$user->id)->where('action','db_capped_action')->where('delta','>',0)->sum('delta'));
    }

    public function test_inactive_database_rule_does_not_fall_back_to_config(): void
    {
        $user = User::factory()->create();
        config(['reputation.weights.disabled_action' => 50]);
        ReputationRule::create(['key'=>'disabled_action','label'=>'Disabled action','weight'=>50,'daily_cap'=>null,'active'=>false]);
        $result = app(ReputationService::class)->applyAction($user,'disabled_action',[],null,'unit.test');
        $this->assertNull($result);
        $this->assertSame(0, UserPointTransaction::where('user_id',$user->id)->where('action','disabled_action')->count());
    }

    public function test_opening_admin_reputation_page_does_not_overwrite_saved_rule_values(): void
    {
        config(['reputation.weights.post_created'=>10,'reputation.daily_caps.post_created'=>40]);
        $rule = ReputationRule::create(['key'=>'post_created','label'=>'Post created','weight'=>77,'daily_cap'=>3,'active'=>false,'description'=>'Admin-authored policy']);
        app(ReputationController::class)->index();
        $rule->refresh();
        $this->assertSame(77,(int)$rule->weight);
        $this->assertSame(3,(int)$rule->daily_cap);
        $this->assertFalse((bool)$rule->active);
        $this->assertSame('Admin-authored policy',$rule->description);
    }

    public function test_transaction_snapshots_dimension_and_convertibility_at_award_time(): void
    {
        $user = User::factory()->create();
        $rule = ReputationRule::create([
            'key'=>'bootstrap_post','label'=>'Bootstrap post','weight'=>7,'daily_cap'=>21,'active'=>true,
            'dimension'=>'participation','convertible'=>true,
        ]);

        $tx = app(ReputationService::class)->applyAction($user,'bootstrap_post',[],123,'group.post');

        $this->assertSame('participation', $tx->dimension);
        $this->assertTrue((bool) $tx->convertible);

        $rule->update(['dimension'=>'civic_trust','convertible'=>false]);
        $tx->refresh();
        $this->assertSame('participation', $tx->dimension, 'Historical transaction policy must not follow later rule edits.');
        $this->assertTrue((bool) $tx->convertible);
    }

    public function test_active_non_convertible_rule_still_awards_social_reputation(): void
    {
        $user = User::factory()->create();
        ReputationRule::create([
            'key'=>'social_only','label'=>'Social only','weight'=>4,'active'=>true,'daily_cap'=>null,
            'dimension'=>'civic_trust','convertible'=>false,
        ]);

        $tx = app(ReputationService::class)->applyAction($user,'social_only',[],null,'unit.test');

        $this->assertNotNull($tx);
        $this->assertSame(4, (int) $tx->delta);
        $this->assertSame('civic_trust', $tx->dimension);
        $this->assertFalse((bool) $tx->convertible);
    }

    public function test_admin_page_preserves_dimension_and_convertibility_policy(): void
    {
        $rule = ReputationRule::create([
            'key'=>'managed_policy','label'=>'Managed policy','weight'=>13,'daily_cap'=>26,'active'=>true,
            'description'=>'Managed by admin','dimension'=>'expertise','convertible'=>false,
        ]);

        app(ReputationController::class)->index();
        $rule->refresh();

        $this->assertSame('expertise', $rule->dimension);
        $this->assertFalse((bool) $rule->convertible);
        $this->assertSame(13, (int) $rule->weight);
        $this->assertSame(26, (int) $rule->daily_cap);
    }

    public function test_admin_can_update_dimension_convertibility_and_repeat_policy(): void
    {
        $rule = ReputationRule::create([
            'key'=>'governance_role','label'=>'Governance role','weight'=>50,'daily_cap'=>null,'active'=>true,
            'dimension'=>'participation','convertible'=>true,'repeat_policy'=>null,
        ]);

        $request = Request::create('/admin/reputation', 'POST', [
            'weights' => ['governance_role' => 75],
            'active' => ['governance_role' => '1'],
            'description' => ['governance_role' => 'Role policy'],
            'daily_cap' => ['governance_role' => null],
            'dimension' => ['governance_role' => 'civic_trust'],
            'convertible' => [],
            'repeat_policy' => ['governance_role' => 'once_per_context'],
        ]);

        app(ReputationController::class)->update($request);
        $rule->refresh();

        $this->assertSame(75, (int) $rule->weight);
        $this->assertSame('civic_trust', $rule->dimension);
        $this->assertFalse((bool) $rule->convertible);
        $this->assertSame('once_per_context', $rule->repeat_policy);
    }

    public function test_admin_reputation_view_exposes_policy_controls(): void
    {
        $source = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringContainsString('name="dimension[{{ $rule->key }}]"', $source);
        $this->assertStringContainsString('name="convertible[{{ $rule->key }}]"', $source);
        $this->assertStringContainsString('name="repeat_policy[{{ $rule->key }}]"', $source);
        $this->assertStringContainsString("'participation'", $source);
        $this->assertStringContainsString("'reliability'", $source);
        $this->assertStringContainsString("'expertise'", $source);
        $this->assertStringContainsString("'civic_trust'", $source);
        $this->assertStringContainsString("'once_per_context'", $source);
    }
}
