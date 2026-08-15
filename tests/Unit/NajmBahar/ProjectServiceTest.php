<?php

namespace Tests\Unit\NajmBahar;

use Tests\TestCase;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class ProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectService $projectService;
    protected User $user;
    protected ProjectCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectService = app(ProjectService::class);
        
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        
        app(AccountService::class)->createMainAccountForUser($this->user->id);

        $this->category = ProjectCategory::create([
            'name' => 'تکنولوژی',
            'level' => 1,
        ]);

        Notification::fake();
    }

    /** @test */
    public function it_can_create_project()
    {
        $data = [
            'title' => 'پروژه تست',
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
            'accept_transparency' => true,
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'خلاصه پروژه',
            'description' => 'توضیحات کامل',
        ];

        $project = $this->projectService->createProject($this->user, $data);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals('draft', $project->status);
        $this->assertEquals($this->user->id, $project->owner_id);
        $this->assertEquals('پروژه تست', $project->title);
    }

    /** @test */
    public function it_can_submit_project_for_review()
    {
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه آماده ارسال',
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
            'description' => 'توضیحات',
        ]);

        $submitted = $this->projectService->submitForReview($project);

        $this->assertEquals('pending', $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    /** @test */
    public function it_cannot_submit_non_draft_project()
    {
        $this->expectException(\Exception::class);

        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه',
            'project_type' => 'infrastructure',
            'project_visibility' => 'private',
            'project_stage' => 'active',
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

        $this->projectService->submitForReview($project);
        
        // تلاش برای ارسال مجدد بدون رد شدن
        $this->projectService->submitForReview($project);
    }

    /** @test */
    public function it_can_start_review()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه',
            'project_type' => 'research',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 500000,
            'base_value_max' => 2000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
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

        $this->projectService->submitForReview($project);
        $reviewed = $this->projectService->startReview($project, $admin);

        $this->assertEquals('under_review', $reviewed->status);
        $this->assertNotNull($reviewed->reviewed_at);
    }

    /** @test */
    public function it_can_approve_project()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه قابل تایید',
            'project_type' => 'social',
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
            'risk_level' => 'medium',
            'oversight_type' => 'guild',
            'reporting_interval' => 'quarterly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $this->projectService->submitForReview($project);
        $this->projectService->startReview($project, $admin);
        
        $approved = $this->projectService->approveProject($project, $admin, 'تایید شد');

        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        
        // بررسی ارسال اعلان
        Notification::assertSentTo($this->user, \App\Notifications\NajmBahar\ProjectStatusChanged::class);
    }

    /** @test */
    public function it_can_reject_project()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه قابل رد',
            'project_type' => 'production',
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
            'auction_period' => 'monthly',
            'risk_level' => 'low',
            'oversight_type' => 'insurance',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $this->projectService->submitForReview($project);
        
        $rejected = $this->projectService->rejectProject(
            $project, 
            $admin, 
            'اطلاعات ناقص', 
            'پروژه رد شد'
        );

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('اطلاعات ناقص', $rejected->rejection_reason);
        
        Notification::assertSentTo($this->user, \App\Notifications\NajmBahar\ProjectStatusChanged::class);
    }

    /** @test */
    public function it_can_request_revision()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه نیازمند اصلاح',
            'project_type' => 'infrastructure',
            'project_visibility' => 'public',
            'project_stage' => 'prototype',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 1500000,
            'base_value_max' => 6000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'quarterly',
            'risk_level' => 'high',
            'oversight_type' => 'both',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'asset_conversion',
            'value_update_trigger' => 'oversight_approval',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $this->projectService->submitForReview($project);
        
        $revised = $this->projectService->requestRevision(
            $project, 
            $admin, 
            'لطفاً توضیحات بیشتری ارائه دهید'
        );

        $this->assertEquals('rejected', $revised->status);
        $this->assertEquals('لطفاً توضیحات بیشتری ارائه دهید', $revised->rejection_reason);
        
        Notification::assertSentTo($this->user, \App\Notifications\NajmBahar\ProjectRevisionRequested::class);
    }

    /** @test */
    public function it_can_archive_project()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه قابل بایگانی',
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
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
        ]);

        $archived = $this->projectService->archiveProject($project, $admin, 'بایگانی توسط ادمین');

        $this->assertEquals('archived', $archived->status);
        $this->assertNotNull($archived->archived_at);
    }

    /** @test */
    public function it_can_get_projects_by_owner()
    {
        $this->projectService->createProject($this->user, [
            'title' => 'پروژه 1',
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 500000,
            'base_value_max' => 1000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'low',
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

        $this->projectService->createProject($this->user, [
            'title' => 'پروژه 2',
            'project_type' => 'service',
            'project_visibility' => 'public',
            'project_stage' => 'prototype',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'target_market' => 'general',
            'base_value_min' => 1000000,
            'base_value_max' => 2000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'medium',
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

        $projects = $this->projectService->getProjectsByOwner($this->user);

        $this->assertCount(2, $projects);
    }

    /** @test */
    public function it_can_get_approved_projects()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه تایید شده',
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'project_type' => 'public',
        ]);

        $this->projectService->submitForReview($project);
        $this->projectService->startReview($project, $admin);
        $this->projectService->approveProject($project, $admin);

        $approvedProjects = $this->projectService->getApprovedProjects();

        $this->assertCount(1, $approvedProjects);
        $this->assertEquals('approved', $approvedProjects->first()->status);
    }
}
