<?php

namespace Database\Factories;

use App\Models\CommunityStory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityStory>
 */
class CommunityStoryFactory extends Factory
{
    protected $model = CommunityStory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->paragraph(3),
            'display_name' => fake()->name(),
            'show_name' => true,
            'avatar_path' => null,
            'show_avatar' => false,
            'role' => fake()->jobTitle(),
            'location' => fake()->city(),
            'locale' => 'fa',
            'status' => CommunityStory::STATUS_PENDING,
            'consent_publication_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'is_featured' => false,
            'published_at' => null,
            'withdrawn_at' => null,
        ];
    }
}
