<?php

namespace Tests\Feature\CommunityStories;

use App\Models\CommunityStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeCommunityStoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('fa');
    }

    public function test_welcome_shows_truthful_empty_state_instead_of_fake_testimonials(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('داستان‌های EarthCoop از همین‌جا آغاز می‌شوند');
        $response->assertSee('به جمع نخستین اعضا بپیوندید');
        $response->assertDontSee('عایشه خان');
        $response->assertDontSee('سه پروژه پایداری');
    }

    public function test_welcome_shows_only_real_publishable_featured_stories_without_filler(): void
    {
        $user = User::factory()->create(['is_system' => false]);

        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'این یک داستان واقعی و تأییدشده از عضو EarthCoop است.',
            'display_name' => 'عضو واقعی',
            'status' => CommunityStory::STATUS_APPROVED,
            'consent_publication_at' => now(),
            'is_featured' => true,
            'published_at' => now(),
        ]);

        CommunityStory::factory()->create([
            'user_id' => $user->id,
            'body' => 'این داستان هنوز در انتظار بررسی است.',
            'display_name' => 'عضو در انتظار',
            'status' => CommunityStory::STATUS_PENDING,
            'consent_publication_at' => now(),
            'is_featured' => true,
            'published_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('این یک داستان واقعی و تأییدشده از عضو EarthCoop است.');
        $response->assertSee('عضو واقعی');
        $response->assertDontSee('این داستان هنوز در انتظار بررسی است.');
        $response->assertDontSee('عایشه خان');
        $response->assertDontSee('داستان‌های EarthCoop از همین‌جا آغاز می‌شوند');
    }
}
