<?php

namespace Database\Factories;

use App\Models\GovernanceArea;
use Illuminate\Database\Eloquent\Factories\Factory;

class GovernanceAreaFactory extends Factory
{
    protected $model = GovernanceArea::class;

    public function definition(): array
    {
        return [
            'key' => 'governance-'.$this->faker->unique()->uuid(),
            'country_code' => 'IR',
            'governance_type' => 'local',
            'area_kind' => 'official',
            'canonical_name' => $this->faker->city(),
            'localized_names' => ['en' => $this->faker->city()],
            'rank' => 500,
            'status' => 'active',
            'metadata' => [],
        ];
    }

    public function official(): static
    {
        return $this->state(fn () => ['area_kind' => 'official']);
    }

    public function community(): static
    {
        return $this->state(fn () => ['area_kind' => 'community']);
    }
}
