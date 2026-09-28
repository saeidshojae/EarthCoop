<?php

namespace Tests\Feature\Seo;

use App\Models\User;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BlogMetadataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_published_post_emits_metadata_and_valid_article_json_ld(): void
    {
        $post = $this->createPost([
            'meta_title' => 'عنوان متای مقاله',
            'meta_description' => 'توضیح متای مقاله',
            'featured_image' => null,
        ]);

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<title>عنوان متای مقاله</title>', $html);
        $this->assertStringContainsString('href="https://earthcoop.ir/blog/'.$post->slug.'"', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
        $payload = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Article', $payload['@type']);
        $this->assertSame($post->author->name, $payload['author']['name']);
        $this->assertSame($post->published_at->toAtomString(), $payload['datePublished']);
        $this->assertArrayNotHasKey('image', $payload);
    }

    public function test_non_public_posts_are_not_accessible(): void
    {
        foreach ([
            ['status' => 'draft'],
            ['status' => 'archived'],
            ['status' => 'published', 'published_at' => now()->addDay()],
        ] as $attributes) {
            $post = $this->createPost($attributes);
            $this->get(route('blog.show', $post->slug))->assertNotFound();
        }

        $deleted = $this->createPost();
        $deleted->delete();
        $this->get(route('blog.show', $deleted->slug))->assertNotFound();
    }

    public function test_blog_index_category_and_tag_have_self_canonicals(): void
    {
        $post = $this->createPost();
        $tag = BlogTag::query()->create(['name' => 'همکاری', 'slug' => 'cooperation']);
        $post->tags()->attach($tag);

        $this->get(route('blog.index'))->assertSee('href="https://earthcoop.ir/blog"', false);
        $this->get(route('blog.category', $post->category->slug))
            ->assertSee('href="https://earthcoop.ir/blog/category/'.$post->category->slug.'"', false);
        $this->get(route('blog.tag', $tag->slug))
            ->assertSee('href="https://earthcoop.ir/blog/tag/'.$tag->slug.'"', false);
    }

    public function test_blog_search_is_noindex_and_drops_query_from_canonical(): void
    {
        $this->createPost();

        $this->get(route('blog.search', ['q' => 'زمین']))
            ->assertOk()
            ->assertSee('content="noindex,follow"', false)
            ->assertSee('href="https://earthcoop.ir/blog/search"', false)
            ->assertDontSee('canonical" href="https://earthcoop.ir/blog/search?q=', false);
    }

    private function createPost(array $attributes = []): Post
    {
        $category = BlogCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'عمومی', 'is_active' => true, 'order' => 0],
        );
        $author = User::factory()->create();

        return Post::query()->create(array_merge([
            'title' => 'مقاله زمین',
            'slug' => 'post-'.uniqid(),
            'excerpt' => 'خلاصه مقاله',
            'content' => '<p>محتوای مقاله درباره زمین و همکاری.</p>',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subHour(),
            'views_count' => 0,
            'is_featured' => false,
            'allow_comments' => true,
        ], $attributes));
    }
}
