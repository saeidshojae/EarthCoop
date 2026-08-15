<?php

namespace Tests\Unit\NajmBahar;

use Tests\TestCase;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
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
        $this->user = User::factory()->create();
        $this->category = ProjectCategory::create([
            'name' => 'تست',
            'level' => 1,
        ]);

        Notification::fake();
    }

    /** @test */
    public function it_can_create_project()
    {
        $data = [
            'title' => 'پروژه تست',
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه پروژه تست',
            'description' => 'توضیحات کامل پروژه تست',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله پروژه تست',
            'solution_description' => 'راه‌حل پروژه تست',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ];

        $project = $this->projectService->createProject($this->user, $data);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals($this->user->id, $project->owner_id);
        $this->assertEquals(User::class, $project->owner_type);
        $this->assertEquals('draft', $project->status);
        $this->assertEquals('پروژه تست', $project->title);
    }

    /** @test */
    public function it_can_submit_project_for_review()
    {
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه تست',
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله',
            'solution_description' => 'شرح راه‌حل',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);

        $submitted = $this->projectService->submitForReview($project);

        $this->assertEquals('submitted', $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    /** @test */
    public function it_can_start_review()
    {
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه تست',
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله',
            'solution_description' => 'شرح راه‌حل',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);
        $this->projectService->submitForReview($project);
        $admin = User::factory()->create(['is_admin' => true]);

        $reviewing = $this->projectService->startReview($project, $admin);

        $this->assertEquals('under_review', $reviewing->status);
        $this->assertNotNull($reviewing->review_started_at);
    }

    /** @test */
    public function it_can_approve_project()
    {
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه تست',
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله',
            'solution_description' => 'شرح راه‌حل',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);
        $this->projectService->submitForReview($project);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->projectService->startReview($project, $admin);

        $approved = $this->projectService->approveProject($project, $admin);

        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
    }

    /** @test */
    public function it_can_reject_project()
    {
        $project = $this->projectService->createProject($this->user, [
            'title' => 'پروژه تست',
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله',
            'solution_description' => 'شرح راه‌حل',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);
        $this->projectService->submitForReview($project);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->projectService->startReview($project, $admin);

        $rejected = $this->projectService->rejectProject($project, $admin, 'دلایل رد پروژه');

        $this->assertEquals('rejected', $rejected->status);
    }

    /** @test */
    public function it_can_get_projects_by_owner()
    {
        $base = [
            'category_level1_id' => $this->category->id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 10000000,
            'profit_percentage' => 15,
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله',
            'solution_description' => 'شرح راه‌حل',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ];

        $this->projectService->createProject($this->user, array_merge($base, ['title' => 'پروژه 1']));
        $this->projectService->createProject($this->user, array_merge($base, ['title' => 'پروژه 2']));

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
            'investment_duration_months' => 12,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'شرح مسئله پروژه تایید شده',
            'solution_description' => 'راه‌حل پروژه تایید شده',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);

        $this->projectService->submitForReview($project);
        $this->projectService->startReview($project, $admin);
        $this->projectService->approveProject($project, $admin);

        $approvedProjects = $this->projectService->getApprovedProjects();

        $this->assertCount(1, $approvedProjects);
        $this->assertEquals('approved', $approvedProjects->first()->status);
    }
}
