<?php

namespace Tests\Feature\CommunityStories;

use App\Models\CommunityStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityStoryPublicationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_featured_returns_only_publishable_featured_stories_in_newest_order_and_limits_to_three(): void
    {
        $user = User::factory()->create(['is_system' => false]);

        $eligible = collect([
            now()->subDays(4),
            now()->subDays(3),
            now()->subDays(2),
            now()->subDay(),
        ])->map(fn ($publishedAt, $index) => CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'eligible-' . $index,
            'status' => CommunityStory::STATUS_APPROVED,
            'consent_publication_at' => now()->subDays(5),
            'is_featured' => true,
            'published_at' => $publishedAt,
        ]));

        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'pending',
            'status' => CommunityStory::STATUS_PENDING,
            'consent_publication_at' => now(),
            'is_featured' => true,
            'published_at' => now(),
        ]);
        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'rejected',
            'status' => CommunityStory::STATUS_REJECTED,
            'consent_publication_at' => now(),
            'is_featured' => true,
            'published_at' => now(),
        ]);
        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'withdrawn',
            'status' => CommunityStory::STATUS_WITHDRAWN,
            'consent_publication_at' => null,
            'is_featured' => true,
            'published_at' => now(),
        ]);
        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'not-featured',
            'status' => CommunityStory::STATUS_APPROVED,
            'consent_publication_at' => now(),
            'is_featured' => false,
            'published_at' => now(),
        ]);
        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'no-consent',
            'status' => CommunityStory::STATUS_APPROVED,
            'consent_publication_at' => null,
            'is_featured' => true,
            'published_at' => now(),
        ]);
        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'not-published',
            'status' => CommunityStory::STATUS_APPROVED,
            'consent_publication_at' => now(),
            'is_featured' => true,
            'published_at' => null,
        ]);

        $stories = CommunityStory::welcomeFeatured();

        $this->assertCount(3, $stories);
        $this->assertSame(
            $eligible->sortByDesc('published_at')->take(3)->pluck('id')->values()->all(),
            $stories->pluck('id')->all()
        );
        $this->assertFalse($stories->contains(fn (CommunityStory $story) => in_array($story->body, [
            'pending', 'rejected', 'withdrawn', 'not-featured', 'no-consent', 'not-published',
        ], true)));
    }
}
