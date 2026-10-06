<?php

namespace Tests\Feature\Seo;

use App\Models\Page;
use App\Models\User;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sitemap_is_valid_xml_with_canonical_static_entries(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $locations = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
        $this->assertSame(1, count(array_keys($locations, 'https://earthcoop.ir/')));
        $this->assertContains('https://earthcoop.ir/terms', $locations);
        $this->assertContains('https://earthcoop.ir/privacy', $locations);
        $this->assertContains('https://earthcoop.ir/blog', $locations);
        foreach ($locations as $location) {
            $this->assertStringStartsWith('https://earthcoop.ir', $location);
        }
    }

    public function test_published_pillars_are_each_present_once(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml);
        $locations = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));

        foreach ([
            'https://earthcoop.ir/economy',
            'https://earthcoop.ir/economy/glass',
            'https://earthcoop.ir/governance',
            'https://earthcoop.ir/governance/elections',
            'https://earthcoop.ir/justice',
            'https://earthcoop.ir/commons',
            'https://earthcoop.ir/cooperative',
            'https://earthcoop.ir/cooperative/global',
            'https://earthcoop.ir/governance/local-to-global',
            'https://earthcoop.ir/economy/ownership',
            'https://earthcoop.ir/technology',
        ] as $pillar) {
            $this->assertSame(1, count(array_keys($locations, $pillar)), $pillar.' must appear exactly once.');
        }
    }

    public function test_only_published_pages_and_posts_are_included_with_lastmod(): void
    {
        $publishedPage = $this->page('public-page', true);
        $this->page('private-page', false);
        $publishedPost = $this->createPost('public-post');
        $this->createPost('draft-post', ['status' => 'draft']);
        $this->createPost('future-post', ['published_at' => now()->addDay()]);
        $deletedPost = $this->createPost('deleted-post');
        $deletedPost->delete();

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('https://earthcoop.ir/pages/'.$publishedPage->slug, $content);
        $this->assertStringContainsString('https://earthcoop.ir/blog/'.$publishedPost->slug, $content);
        $this->assertStringContainsString('<lastmod>', $content);
        foreach (['private-page', 'draft-post', 'future-post', 'deleted-post'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $content);
        }
    }

    public function test_private_and_thin_routes_are_not_in_sitemap(): void
    {
        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (['/login', '/register', '/admin', '/api', '/invitation', '/blog/search', 'docs.earthcoop.ir'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $content);
        }
    }

    public function test_static_sitemap_survives_when_optional_tables_are_absent(): void
    {
        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->with('pages')
            ->andReturnFalse();
        Schema::shouldReceive('hasTable')
            ->with('blog_posts')
            ->andReturnFalse();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('https://earthcoop.ir/terms');
    }

    public function test_robots_advertises_only_the_canonical_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString("User-agent: *\nDisallow:", $robots);
        $this->assertSame(1, substr_count($robots, 'Sitemap:'));
        $this->assertStringContainsString('Sitemap: https://earthcoop.ir/sitemap.xml', $robots);
    }

    private function page(string $slug, bool $published): Page
    {
        return Page::query()->create([
            'title' => $slug,
            'slug' => $slug,
            'template' => 'default',
            'content' => 'content',
            'is_published' => $published,
            'show_in_header' => false,
        ]);
    }

    private function createPost(string $slug, array $attributes = []): Post
    {
        $category = BlogCategory::query()->firstOrCreate(
            ['slug' => 'sitemap'],
            ['name' => 'Sitemap', 'is_active' => true, 'order' => 0],
        );

        return Post::query()->create(array_merge([
            'title' => $slug,
            'slug' => $slug,
            'content' => 'content',
            'category_id' => $category->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'published',
            'published_at' => now()->subHour(),
        ], $attributes));
    }
}
