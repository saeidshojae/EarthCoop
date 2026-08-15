<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_outsider_cannot_view_or_invest_in_approved_private_project(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = $this->approvedProject($owner, 'private');

        $this->actingAs($outsider)
            ->get(route('najm-bahar.investments.show', $project))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('najm-bahar.investments.store', $project), ['amount' => 100])
            ->assertForbidden();
    }

    public function test_public_approved_project_remains_visible_to_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $investor = User::factory()->create();
        $project = $this->approvedProject($owner, 'public');

        $this->actingAs($investor)
            ->get(route('najm-bahar.investments.show', $project))
            ->assertOk();
    }

    private function approvedProject(User $owner, string $visibility)
    {
        $category = ProjectCategory::create([
            'name' => 'امنیت تست سرمایه‌گذاری',
            'level' => 1,
        ]);

        $service = app(ProjectService::class);
        $project = $service->createProject($owner, [
            'title' => 'پروژه تست دسترسی',
            'category_level1_id' => $category->id,
            'project_type' => 'production',
            'project_visibility' => $visibility,
            'investment_method' => 'capital_participation',
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'problem_statement' => 'مسئله تست',
            'solution_description' => 'راه‌حل تست',
            'required_capital' => 100_000,
            'profit_percentage' => 10,
            'investment_duration_months' => 12,
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);

        $service->submitForReview($project);
        $admin = User::factory()->create(['is_admin' => true]);
        $service->startReview($project, $admin);

        return $service->approveProject($project, $admin);
    }
}
