<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'canonical_name' => $name,
            'status' => 'active',
            'localized_names' => [],
            'provenance' => ['source' => 'test-fixture'],
        ];
    }
}
