<?php

namespace Tests\Feature\Seo;

use App\Models\User;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\Post;
use Database\Seeders\PersianSeoBlogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PersianSeoBlogSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const CATEGORY_SLUGS = [
        'economy-ownership',
        'governance-elections',
        'cooperation-community',
        'justice-commons',
        'technology-transparency',
    ];

    private const POSTS = [
        'people-economy-explained' => '/economy',
        'free-market-without-monopoly' => '/economy',
        'financial-transparency-and-privacy' => '/economy/glass',
        'participatory-governance-beyond-voting' => '/governance',
        'continuous-elections-explained' => '/governance/elections',
        'platform-cooperative-and-earthcoop' => '/cooperative',
        'earth-in-earthcoop-justice' => '/justice',
        'private-property-and-common-resources' => '/economy/ownership',
    ];

    public function test_it_publishes_the_approved_taxonomy_and_first_eight_deep_articles(): void
    {
        $this->supportAuthor();

        $this->seed(PersianSeoBlogSeeder::class);

        foreach (self::CATEGORY_SLUGS as $slug) {
            $this->assertDatabaseHas('blog_categories', [
                'slug' => $slug,
                'is_active' => true,
            ]);
        }

        foreach (self::POSTS as $slug => $pillarPath) {
            $post = Post::query()->where('slug', $slug)->firstOrFail();

            $this->assertSame('published', $post->status, $slug);
            $this->assertNotNull($post->published_at, $slug);
            $this->assertNotEmpty($post->meta_title, $slug);
            $this->assertNotEmpty($post->meta_description, $slug);
            $this->assertNotEmpty($post->excerpt, $slug);
            $this->assertStringContainsString('href="'.$pillarPath.'"', $post->content, $slug);
            $this->assertStringContainsString('https://docs.earthcoop.ir/', $post->content, $slug);
            $this->assertGreaterThan(1800, mb_strlen(strip_tags($post->content)), $slug.' should be substantive, not thin SEO copy.');
        }
    }

    public function test_wave_one_articles_are_present_in_the_sitemap_after_seeding(): void
    {
        $this->supportAuthor();
        $this->seed(PersianSeoBlogSeeder::class);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (array_keys(self::POSTS) as $slug) {
            $this->assertStringContainsString(
                'https://earthcoop.ir/blog/'.$slug,
                $content,
                $slug.' must remain discoverable through the canonical sitemap.'
            );
        }
    }

    public function test_it_is_idempotent_and_does_not_delete_unrelated_existing_blog_content(): void
    {
        $author = $this->supportAuthor();
        $legacyCategory = BlogCategory::query()->create([
            'name' => 'Legacy',
            'slug' => 'legacy-preserved',
            'description' => 'Existing production content sentinel',
            'is_active' => true,
            'order' => 99,
        ]);

        Post::query()->create([
            'title' => 'Existing production article',
            'slug' => 'existing-production-article',
            'excerpt' => 'Must survive the SEO content seeder.',
            'content' => '<p>Existing content</p>',
            'category_id' => $legacyCategory->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->seed(PersianSeoBlogSeeder::class);
        $firstPublishedAt = Post::query()->where('slug', 'people-economy-explained')->value('published_at');
        $this->seed(PersianSeoBlogSeeder::class);

        $this->assertDatabaseHas('blog_posts', ['slug' => 'existing-production-article']);
        $this->assertSame(1, Post::query()->where('slug', 'existing-production-article')->count());

        foreach (array_keys(self::POSTS) as $slug) {
            $this->assertSame(1, Post::query()->where('slug', $slug)->count(), $slug);
        }

        $this->assertSame(
            $firstPublishedAt?->format('Y-m-d H:i:s'),
            Post::query()->where('slug', 'people-economy-explained')->value('published_at')?->format('Y-m-d H:i:s'),
            'Re-seeding must not rewrite the original publication timestamp.'
        );
    }

    private function supportAuthor(): User
    {
        return User::factory()->create([
            'email' => 'support@earthcoop.ir',
        ]);
    }
}
