<?php

namespace Database\Factories\Modules\NajmBahar\Models;

use App\Modules\NajmBahar\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectCategoryFactory extends Factory
{
    protected $model = ProjectCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'level' => 1,
            'parent_id' => null,
            'order' => $this->faker->numberBetween(1, 20),
            'status' => true,
        ];
    }

    public function level2(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 2,
            'parent_id' => ProjectCategory::factory()->create()->id,
        ]);
    }

    public function level3(): static
    {
        return $this->state(function (array $attributes) {
            $level2 = ProjectCategory::factory()->level2()->create();
            return [
                'level' => 3,
                'parent_id' => $level2->id,
            ];
        });
    }
}
