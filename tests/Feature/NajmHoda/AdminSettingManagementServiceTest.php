<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FounderAdminSettingIntent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminSettingManagementService;
use App\Services\NajmHoda\FounderOps\FounderAdminSettingDecisionService;
use App\Services\NajmHoda\FounderOps\FounderAdminSettingRecommendationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class AdminSettingManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default'=>'array',
            'najm-hoda.runtime.autonomy.human_escalation.enabled'=>true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins'=>false,
        ]);
        Cache::flush();
    }

    public function test_recommendation_is_non_mutating_and_bounded(): void
    {
        $setting = Setting::singleton();
        $setting->forceFill(['count_invation'=>10])->save();

        $result = app(AdminSettingManagementService::class)->recommend('count_invation', 25);

        $this->assertTrue($result['success']);
        $this->assertSame('proposed', $result['status']);
        $this->assertSame(10, (int) Setting::query()->findOrFail(1)->count_invation);
        $this->assertSame(25, $result['proposed_value']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_allowed_setting_can_change_through_canonical_boundary(): void
    {
        Setting::singleton();

        $result = app(AdminSettingManagementService::class)->change('finger_status', true);

        $this->assertSame('changed', $result['status']);
        $this->assertTrue((bool) Setting::query()->findOrFail(1)->finger_status);
    }

    public function test_founder_approval_executes_exact_persisted_setting_intent(): void
    {
        $founder = User::query()->create([
            'email'=>uniqid('founder-setting-', true).'@example.test',
            'password'=>Hash::make('password'),
            'status'=>1,
            'first_name'=>'Founder',
            'last_name'=>'Approver',
            'is_system'=>false,
        ]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);

        $setting = Setting::singleton();
        $setting->forceFill(['count_invation'=>10])->save();

        $service = app(FounderAdminSettingDecisionService::class);
        $request = $service->requestChange('count_invation', 42, $founder->id, 'setting-intent-e2e-'.uniqid());

        $this->assertSame('awaiting_approval', $request['status']);
        $requestId = (string) data_get($request, 'approval_request.id');
        $intentId = (int) data_get($request, 'approval_request.context.entity_id');
        $intent = FounderAdminSettingIntent::query()->findOrFail($intentId);
        $this->assertSame('count_invation', $intent->setting_key);
        $this->assertSame(42, (int) data_get($intent->setting_value, 'value'));
        $this->assertSame(10, (int) Setting::query()->findOrFail(1)->count_invation);

        $result = $service->decideAndExecute($requestId, 'approve', $founder->id, 'approved for test');

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame(42, (int) Setting::query()->findOrFail(1)->count_invation);
        $this->assertSame(FounderAdminSettingIntent::EXECUTED, $intent->fresh()->status);
        $this->assertSame($founder->id, (int) $intent->fresh()->executed_by);
    }

    public function test_financial_setting_is_not_delegable_through_generic_admin_boundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('admin_setting_key_not_delegable');

        app(AdminSettingManagementService::class)->recommend('najm_bahar_initial_amount', 100000);
    }

    public function test_reputation_monetary_setting_is_not_delegable_through_generic_admin_boundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('admin_setting_key_not_delegable');

        app(AdminSettingManagementService::class)->change('reputation_to_gol_ratio', 10);
    }

    public function test_connectivity_registry_exposes_bounded_setting_adapters_and_blocks_role_permission_changes(): void
    {
        $proposals = (array) config('najm-hoda-founder-connectivity.proposal_adapters', []);
        $approvals = (array) config('najm-hoda-founder-connectivity.approval_adapters', []);
        $blocked = (array) config('najm-hoda-founder-connectivity.blocked_actions', []);

        $this->assertSame(FounderAdminSettingRecommendationService::class, $proposals['admin_settings.recommend_change'] ?? null);
        $this->assertSame(FounderAdminSettingDecisionService::class, $approvals['admin_settings.change_setting'] ?? null);
        $this->assertSame('canonical_role_permission_boundary_missing', $blocked['admin_settings.change_role_permission']['reason'] ?? null);
    }
}
