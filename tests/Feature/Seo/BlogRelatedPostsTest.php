<?php

namespace Tests\Feature\Seo;

use App\Models\User;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BlogRelatedPostsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seo_article_prefers_curated_published_siblings_in_registry_order(): void
    {
        $category = $this->category('economy', 'اقتصاد');
        $author = User::factory()->create();

        $current = $this->createPost($category, $author, [
            'slug' => 'people-economy-explained',
            'title' => 'اقتصاد مردمی چیست؟ از مشارکت اقتصادی تا اقتصاد آزاد مردمی',
            'published_at' => now()->subDays(3),
        ]);

        $curatedSibling = $this->createPost($category, $author, [
            'slug' => 'free-market-without-monopoly',
            'title' => 'بازار آزاد بدون انحصار چگونه ممکن است؟',
            'published_at' => now()->subDays(5),
        ]);

        $sameCategoryButNotCurated = $this->createPost($category, $author, [
            'slug' => 'economy-unrelated-newer',
            'title' => 'مقاله جدیدتر اما خارج از نگاشت معنایی',
            'published_at' => now()->subHour(),
        ]);

        $response = $this->get(route('blog.show', $current->slug))->assertOk();

        $response->assertSee($curatedSibling->title);
        $response->assertDontSee($sameCategoryButNotCurated->title);
        $response->assertDontSee('href="'.route('blog.show', $current->slug).'"', false);
    }

    public function test_unpublished_curated_sibling_is_not_rendered(): void
    {
        $category = $this->category('economy', 'اقتصاد');
        $author = User::factory()->create();

        $current = $this->createPost($category, $author, [
            'slug' => 'people-economy-explained',
        ]);

        $draftSibling = $this->createPost($category, $author, [
            'slug' => 'free-market-without-monopoly',
            'title' => 'بازار آزاد بدون انحصار چگونه ممکن است؟',
            'status' => 'draft',
        ]);

        $this->get(route('blog.show', $current->slug))
            ->assertOk()
            ->assertDontSee($draftSibling->title);
    }

    public function test_legacy_article_keeps_same_category_recent_fallback(): void
    {
        $category = $this->category('general', 'عمومی');
        $otherCategory = $this->category('other', 'دیگر');
        $author = User::factory()->create();

        $current = $this->createPost($category, $author, [
            'slug' => 'legacy-current',
            'published_at' => now()->subDays(3),
        ]);

        $sameCategoryRecent = $this->createPost($category, $author, [
            'slug' => 'legacy-related',
            'title' => 'مقاله مرتبط قدیمی',
            'published_at' => now()->subHour(),
        ]);

        $otherCategoryPost = $this->createPost($otherCategory, $author, [
            'slug' => 'other-category-post',
            'title' => 'مقاله دسته دیگر',
            'published_at' => now()->subMinutes(10),
        ]);

        $response = $this->get(route('blog.show', $current->slug))->assertOk();

        $response->assertSee($sameCategoryRecent->title);
        $response->assertDontSee($otherCategoryPost->title);
    }

    private function category(string $slug, string $name): BlogCategory
    {
        return BlogCategory::query()->create([
            'slug' => $slug,
            'name' => $name,
            'is_active' => true,
            'order' => 0,
        ]);
    }

    private function createPost(BlogCategory $category, User $author, array $attributes = []): Post
    {
        return Post::query()->create(array_merge([
            'title' => 'مقاله آزمایشی',
            'slug' => 'post-'.uniqid(),
            'excerpt' => 'خلاصه مقاله',
            'content' => '<p>محتوای مقاله.</p>',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'views_count' => 0,
            'is_featured' => false,
            'allow_comments' => true,
        ], $attributes));
    }
}
