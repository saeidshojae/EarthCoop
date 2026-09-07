<?php

namespace Tests\Feature\CommunityStories;

use App\Models\CommunityStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityStorySubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_cannot_access_member_story_submission_page(): void
    {
        $response = $this->get(route('community-stories.index'));

        $this->assertTrue($response->isRedirect());
    }

    public function test_member_can_submit_story_only_with_explicit_publication_consent(): void
    {
        $user = User::factory()->create([
            'is_system' => false,
            'first_name' => 'سعید',
            'last_name' => 'آزمون',
            'avatar' => 'member-avatar.jpg',
        ]);

        $withoutConsent = $this->actingAs($user)->post(route('community-stories.store'), [
            'body' => 'این تجربه برای آزمون جریان داستان واقعی عضو نوشته شده است و به اندازه کافی توضیح دارد.',
            'show_name' => '1',
        ]);
        $withoutConsent->assertSessionHasErrors('consent_publication');
        $this->assertDatabaseCount('community_stories', 0);

        $response = $this->actingAs($user)->post(route('community-stories.store'), [
            'body' => 'این تجربه برای آزمون جریان داستان واقعی عضو نوشته شده است و به اندازه کافی توضیح دارد.',
            'show_name' => '1',
            'show_avatar' => '1',
            'role' => 'عضو آزمایشی',
            'location' => 'ساری',
            'consent_publication' => '1',
        ]);

        $response->assertRedirect(route('community-stories.index'));
        $story = CommunityStory::firstOrFail();
        $this->assertSame($user->id, $story->user_id);
        $this->assertSame(CommunityStory::STATUS_PENDING, $story->status);
        $this->assertNotNull($story->consent_publication_at);
        $this->assertTrue($story->show_name);
        $this->assertSame('سعید آزمون', $story->display_name);
        $this->assertTrue($story->show_avatar);
        $this->assertSame('images/users/avatars/member-avatar.jpg', $story->avatar_path);
        $this->assertFalse($story->is_featured);
        $this->assertNull($story->published_at);
    }

    public function test_member_submission_page_lists_only_own_stories(): void
    {
        $user = User::factory()->create(['is_system' => false]);
        $other = User::factory()->create(['is_system' => false]);

        CommunityStory::factory()->create(['user_id' => $user->id, 'body' => 'داستان خود کاربر']);
        CommunityStory::factory()->create(['user_id' => $other->id, 'body' => 'داستان کاربر دیگر']);

        $response = $this->actingAs($user)->get(route('community-stories.index'));

        $response->assertOk();
        $response->assertSee('داستان خود کاربر');
        $response->assertDontSee('داستان کاربر دیگر');
    }
}
