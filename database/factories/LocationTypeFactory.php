<?php

namespace Database\Factories;

use App\Models\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocationTypeFactory extends Factory
{
    protected $model = LocationType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name, '_'),
            'canonical_name' => Str::title($name),
            'is_residence_endpoint' => false,
            'metadata' => [],
        ];
    }
}
