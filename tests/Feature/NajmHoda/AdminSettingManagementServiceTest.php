<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Setting;
use App\Services\Admin\AdminSettingManagementService;
use App\Services\NajmHoda\FounderOps\FounderAdminSettingDecisionService;
use App\Services\NajmHoda\FounderOps\FounderAdminSettingRecommendationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class AdminSettingManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recommendation_is_non_mutating_and_bounded(): void
    {
        $setting = Setting::query()->firstOrCreate(['id'=>1]);
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
        Setting::query()->firstOrCreate(['id'=>1]);

        $result = app(AdminSettingManagementService::class)->change('finger_status', true);

        $this->assertSame('changed', $result['status']);
        $this->assertTrue((bool) Setting::query()->findOrFail(1)->finger_status);
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
