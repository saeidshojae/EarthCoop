<?php

namespace Database\Factories;

use App\Models\LocationSchema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocationSchemaFactory extends Factory
{
    protected $model = LocationSchema::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'key' => Str::slug($name, '-'),
            'country_code' => strtoupper(fake()->lexify('??')),
            'name' => Str::title($name),
            'version' => '1',
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
