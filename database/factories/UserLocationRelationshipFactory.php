<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use App\Models\UserLocationRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserLocationRelationshipFactory extends Factory
{
    protected $model = UserLocationRelationship::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'location_id' => Location::factory(),
            'relationship_type' => 'other',
            'started_at' => now(),
            'ended_at' => null,
            'evidence' => [],
            'explicit_transfer' => false,
            'transfer_override' => false,
            'changed_by_user_id' => null,
            'change_reason' => null,
            'metadata' => [],
        ];
    }

    public function primaryResidence(): static
    {
        return $this->state(fn () => ['relationship_type' => 'primary_residence']);
    }

    public function work(): static
    {
        return $this->state(fn () => ['relationship_type' => 'work']);
    }

    public function study(): static
    {
        return $this->state(fn () => ['relationship_type' => 'study']);
    }

    public function ended(): static
    {
        return $this->state(fn () => ['ended_at' => now()]);
    }
}
