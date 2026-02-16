<?php

namespace Database\Factories\Modules\NajmBahar\Models;

use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'category_level1_id' => ProjectCategory::factory(),
            'category_level2_id' => null,
            'category_level3_id' => null,
            'title' => $this->faker->sentence(4),
            'summary' => $this->faker->paragraph(2),
            'description' => $this->faker->paragraphs(5, true),
            'required_capital' => $this->faker->numberBetween(1000000, 100000000), // 10K - 1M گل
            'profit_percentage' => $this->faker->numberBetween(5, 30),
            'investment_duration_months' => $this->faker->randomElement([6, 12, 18, 24, 36]),
            'project_type' => $this->faker->randomElement(['public', 'private']),
            'status' => 'draft',
            'attachments' => [],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(3),
            'approved_at' => now()->subDays(2),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'submitted_at' => now()->subDays(2),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(3),
            'rejection_reason' => 'اطلاعات ناکافی',
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'under_review',
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(1),
        ]);
    }
}
