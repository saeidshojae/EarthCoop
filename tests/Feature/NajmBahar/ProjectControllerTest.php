<?php

namespace Tests\Feature\NajmBahar;

use Tests\TestCase;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\AccountNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ProjectCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // ایجاد کاربر تست
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // ایجاد حساب نجم بهار برای کاربر
        $accountService = app(AccountService::class);
        $accountService->createMainAccountForUser($this->user->id);

        // ایجاد دسته‌بندی تست
        $this->category = ProjectCategory::create([
            'name' => 'کشاورزی',
            'level' => 1,
            'parent_id' => null,
        ]);

        Notification::fake();
    }

    /** @test */
    public function user_can_view_projects_list()
    {
        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.index'));

        $response->assertOk()
            ->assertViewIs('najm-bahar.projects.index');
    }

    /** @test */
    public function user_can_view_create_project_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk()
            ->assertViewIs('najm-bahar.projects.create')
            ->assertViewHas('categories');
    }

    /** @test */
    public function user_can_create_project()
    {
        $data = [
            'title' => 'پروژه تست کشاورزی',
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'بیان مسئله',
            'solution_description' => 'توضیح راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 1000000,
            'base_value_max' => 5000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'medium',
            'oversight_type' => 'guild',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => '1',
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => '1',
            'summary' => 'خلاصه پروژه تست',
            'description' => 'توضیحات کامل پروژه تست',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $data);

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('najm_bahar_projects', [
            'title' => 'پروژه تست کشاورزی',
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function user_can_submit_project_for_review()
    {
        $project = app(ProjectService::class)->createProject($this->user, [
            'title' => 'پروژه آماده بررسی',
            'project_type' => 'service',
            'project_visibility' => 'public',
            'project_stage' => 'prototype',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 2000000,
            'base_value_max' => 8000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'quarterly',
            'risk_level' => 'low',
            'oversight_type' => 'insurance',
            'reporting_interval' => 'quarterly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'asset_conversion',
            'value_update_trigger' => 'oversight_approval',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات کامل',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.submit', $project));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('pending', $project->fresh()->status);
    }

    /** @test */
    public function user_cannot_submit_invalid_project()
    {
        $project = Project::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'status' => 'draft',
            'investment_method' => 'auction_shares',
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'accept_transparency' => true,
            'accept_rules' => true,
            'base_value_min' => 0, // مقدار نامعتبر
            'base_value_max' => 0, // مقدار نامعتبر
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.submit', $project));

        $response->assertSessionHas('error');
        $this->assertEquals('draft', $project->fresh()->status);
    }

    /** @test */
    public function user_can_edit_own_draft_project()
    {
        $project = app(ProjectService::class)->createProject($this->user, [
            'title' => 'پروژه اولیه',
            'project_type' => 'infrastructure',
            'project_visibility' => 'private',
            'project_stage' => 'idea',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 500000,
            'base_value_max' => 2000000,
            'total_shares' => 100,
            'initial_auction_percent' => 25,
            'max_user_ownership_percent' => 15,
            'auction_period' => 'semi_annual',
            'risk_level' => 'high',
            'oversight_type' => 'both',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'vote',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.edit', $project));

        $response->assertOk()
            ->assertViewIs('najm-bahar.projects.edit')
            ->assertViewHas('project', $project);
    }

    /** @test */
    public function user_cannot_edit_others_project()
    {
        $otherUser = User::factory()->create();
        
        $project = app(ProjectService::class)->createProject($otherUser, [
            'title' => 'پروژه دیگری',
            'project_type' => 'research',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 1000000,
            'base_value_max' => 4000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'medium',
            'oversight_type' => 'guild',
            'reporting_interval' => 'quarterly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'asset_conversion',
            'value_update_trigger' => 'oversight_approval',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.edit', $project));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_delete_own_draft_project()
    {
        $project = app(ProjectService::class)->createProject($this->user, [
            'title' => 'پروژه قابل حذف',
            'project_type' => 'social',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 500000,
            'base_value_max' => 1500000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'annual',
            'risk_level' => 'medium',
            'oversight_type' => 'guild',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('najm-bahar.projects.destroy', $project));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('najm_bahar_projects', ['id' => $project->id]);
    }

    /** @test */
    public function user_cannot_delete_submitted_project()
    {
        $project = app(ProjectService::class)->createProject($this->user, [
            'title' => 'پروژه ارسال شده',
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'prototype',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 1000000,
            'base_value_max' => 3000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'low',
            'oversight_type' => 'insurance',
            'reporting_interval' => 'quarterly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'asset_conversion',
            'value_update_trigger' => 'oversight_approval',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        app(ProjectService::class)->submitForReview($project);

        $response = $this->actingAs($this->user)
            ->delete(route('najm-bahar.projects.destroy', $project));

        $response->assertForbidden();
        $this->assertDatabaseHas('najm_bahar_projects', ['id' => $project->id]);
    }

    /** @test */
    public function ajax_can_load_subcategories()
    {
        $level2 = ProjectCategory::create([
            'name' => 'گندم',
            'level' => 2,
            'parent_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('najm-bahar.projects.categories.sub', [
                'parent_id' => $this->category->id
            ]));

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $level2->id,
                'name' => 'گندم'
            ]);
    }
}
