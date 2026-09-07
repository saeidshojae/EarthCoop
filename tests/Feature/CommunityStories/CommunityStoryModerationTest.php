<?php

namespace Tests\Feature\CommunityStories;

use App\Models\CommunityStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityStoryModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_regular_member_cannot_access_story_moderation(): void
    {
        $member = User::factory()->create(['is_system' => false, 'is_admin' => false]);

        $response = $this->actingAs($member)->get(route('admin.community-stories.index'));

        $this->assertTrue($response->isRedirect() || $response->status() === 403);
    }

    public function test_admin_can_approve_publish_and_feature_only_a_consented_story(): void
    {
        $admin = User::factory()->create(['is_system' => false, 'is_admin' => true]);
        $member = User::factory()->create(['is_system' => false]);
        $story = CommunityStory::factory()->create([
            'user_id' => $member->id,
            'status' => CommunityStory::STATUS_PENDING,
            'consent_publication_at' => now(),
            'is_featured' => false,
            'published_at' => null,
        ]);

        $approve = $this->actingAs($admin)->post(route('admin.community-stories.approve', $story));
        $approve->assertRedirect(route('admin.community-stories.index'));

        $story->refresh();
        $this->assertSame(CommunityStory::STATUS_APPROVED, $story->status);
        $this->assertSame($admin->id, $story->reviewed_by);
        $this->assertNotNull($story->reviewed_at);
        $this->assertNotNull($story->published_at);

        $feature = $this->actingAs($admin)->post(route('admin.community-stories.feature', $story));
        $feature->assertRedirect(route('admin.community-stories.index'));
        $this->assertTrue($story->fresh()->is_featured);
    }

    public function test_story_without_active_consent_cannot_be_approved_or_featured(): void
    {
        $admin = User::factory()->create(['is_system' => false, 'is_admin' => true]);
        $member = User::factory()->create(['is_system' => false]);
        $story = CommunityStory::factory()->create([
            'user_id' => $member->id,
            'status' => CommunityStory::STATUS_PENDING,
            'consent_publication_at' => null,
            'is_featured' => false,
            'published_at' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.community-stories.approve', $story));
        $response->assertSessionHasErrors('story');
        $this->assertSame(CommunityStory::STATUS_PENDING, $story->fresh()->status);

        $response = $this->actingAs($admin)->post(route('admin.community-stories.feature', $story));
        $response->assertSessionHasErrors('story');
        $this->assertFalse($story->fresh()->is_featured);
    }

    public function test_rejecting_story_removes_it_from_publication_and_featured_state(): void
    {
        $admin = User::factory()->create(['is_system' => false, 'is_admin' => true]);
        $member = User::factory()->create(['is_system' => false]);
        $story = CommunityStory::factory()->create([
            'user_id' => $member->id,
            'status' => CommunityStory::STATUS_APPROVED,
            'consent_publication_at' => now(),
            'is_featured' => true,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.community-stories.reject', $story), [
            'review_note' => 'برای انتشار عمومی مناسب نیست.',
        ]);
        $response->assertRedirect(route('admin.community-stories.index'));

        $story->refresh();
        $this->assertSame(CommunityStory::STATUS_REJECTED, $story->status);
        $this->assertFalse($story->is_featured);
        $this->assertNull($story->published_at);
        $this->assertSame('برای انتشار عمومی مناسب نیست.', $story->review_note);
    }
}
