<?php

namespace App\Modules\Blog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Models\BlogComment;
use App\Modules\Blog\Requests\CommentRequest;
use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\PillarArticleRegistry;
use App\Support\Seo\PillarRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function __construct(private readonly CanonicalUrl $canonicalUrl)
    {
    }

    /**
     * Display blog homepage
     */
    public function index(Request $request)
    {
        $query = Post::with(['category', 'author', 'tags'])
                    ->published()
                    ->orderBy('published_at', 'desc');

        // Search
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category_id', $request->category);
        }

        // Filter by tag
        if ($request->has('tag') && $request->tag) {
            $query->whereHas('tags', function($q) use ($request) {
                $q->where('blog_tags.id', $request->tag);
            });
        }

        $posts = $query->paginate(12);
        
        $categories = BlogCategory::active()->ordered()->get();
        $popularPosts = Post::published()->popular(5)->get();
        $featuredPosts = Post::published()->featured()->recent(3)->get();
        $tags = BlogTag::has('posts')->get();

        return view('Blog::frontend.index', array_merge(
            compact('posts', 'categories', 'popularPosts', 'featuredPosts', 'tags'),
            $this->metadata(
                'بلاگ ارث‌کوپ',
                'مقاله‌ها، خبرها و تجربه‌های جامعه ارث‌کوپ درباره همکاری، زمین و آینده مشترک.',
                '/blog',
            ),
        ));
    }

    /**
     * Display single post
     */
    public function show($slug)
    {
        $post = Post::with(['category', 'author', 'tags', 'approvedComments.user', 'approvedComments.replies.user'])
                    ->where('slug', $slug)
                    ->published()
                    ->firstOrFail();

        // Increment views
        $post->incrementViews();

        $pillarKey = PillarArticleRegistry::ownerForSlug($post->slug);

        if ($pillarKey !== null) {
            $curatedSlugs = collect(PillarArticleRegistry::for($pillarKey))
                ->pluck('path')
                ->map(static fn (string $path): string => basename($path))
                ->reject(static fn (string $slug): bool => $slug === $post->slug)
                ->values();

            $relatedPosts = $curatedSlugs->isEmpty()
                ? collect()
                : Post::published()
                    ->whereIn('slug', $curatedSlugs->all())
                    ->get()
                    ->sortBy(static fn (Post $relatedPost): int => $curatedSlugs->search($relatedPost->slug))
                    ->values();
        } else {
            $relatedPosts = Post::published()
                                ->where('category_id', $post->category_id)
                                ->where('id', '!=', $post->id)
                                ->recent(4)
                                ->get();
        }

        $categories = BlogCategory::active()->ordered()->get();
        $popularPosts = Post::published()->popular(5)->get();
        $tags = BlogTag::has('posts')->get();

        $canonical = $this->canonicalUrl->to('/blog/'.$post->slug);
        $siteUrl = $this->canonicalUrl->to('/');
        $description = Str::limit(strip_tags((string) ($post->meta_description ?: $post->excerpt)), 160);
        $authorName = trim((string) ($post->author?->displayName() ?? ''));
        $pillar = $pillarKey !== null ? PillarRegistry::get($pillarKey) : null;

        $article = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->meta_title ?: $post->title,
            'description' => $description,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'mainEntityOfPage' => $canonical,
            'inLanguage' => 'fa',
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'EarthCoop',
                'url' => $siteUrl,
            ],
            'isPartOf' => [
                '@type' => 'WebSite',
                '@id' => $siteUrl.'#website',
                'url' => $siteUrl,
                'name' => 'EarthCoop',
            ],
            'about' => $pillar !== null ? [
                '@type' => 'Thing',
                'name' => $pillar['title'],
                'url' => $this->canonicalUrl->to($pillar['path']),
            ] : null,
            'author' => $authorName !== '' ? [
                '@type' => 'Person',
                'name' => $authorName,
            ] : null,
            'image' => $post->featured_image
                ? $this->canonicalUrl->to('/images/blog/posts/'.ltrim($post->featured_image, '/'))
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return view('Blog::frontend.show', array_merge(
            compact('post', 'relatedPosts', 'categories', 'popularPosts', 'tags'),
            $this->metadata(
                $post->meta_title ?: $post->title,
                $description,
                '/blog/'.$post->slug,
                type: 'article',
                image: $article['image'] ?? null,
                jsonLd: [$article],
            ),
        ));
    }

    /**
     * Display posts by category
     */
    public function category($slug)
    {
        $category = BlogCategory::where('slug', $slug)->active()->firstOrFail();
        
        $posts = Post::with(['category', 'author', 'tags'])
                    ->published()
                    ->where('category_id', $category->id)
                    ->orderBy('published_at', 'desc')
                    ->paginate(12);

        $categories = BlogCategory::active()->ordered()->get();
        $popularPosts = Post::published()->popular(5)->get();
        $tags = BlogTag::has('posts')->get();

        return view('Blog::frontend.category', array_merge(
            compact('category', 'posts', 'categories', 'popularPosts', 'tags'),
            $this->metadata(
                'دسته‌بندی: '.$category->name,
                Str::limit(strip_tags((string) ($category->description ?: 'مقاله‌های دسته‌بندی '.$category->name.' در بلاگ ارث‌کوپ.')), 160),
                '/blog/category/'.$category->slug,
            ),
        ));
    }

    /**
     * Display posts by tag
     */
    public function tag($slug)
    {
        $tag = BlogTag::where('slug', $slug)->firstOrFail();
        
        $posts = Post::with(['category', 'author', 'tags'])
                    ->published()
                    ->whereHas('tags', function($q) use ($tag) {
                        $q->where('blog_tags.id', $tag->id);
                    })
                    ->orderBy('published_at', 'desc')
                    ->paginate(12);

        $categories = BlogCategory::active()->ordered()->get();
        $popularPosts = Post::published()->popular(5)->get();
        $tags = BlogTag::has('posts')->get();

        return view('Blog::frontend.tag', array_merge(
            compact('tag', 'posts', 'categories', 'popularPosts', 'tags'),
            $this->metadata(
                'برچسب: '.$tag->name,
                'مقاله‌های مرتبط با '.$tag->name.' در بلاگ ارث‌کوپ.',
                '/blog/tag/'.$tag->slug,
            ),
        ));
    }

    /**
     * Store comment
     */
    public function storeComment(CommentRequest $request, Post $post)
    {
        if (!$post->allow_comments) {
            return redirect()->back()->with('error', 'امکان ثبت نظر برای این پست فعال نیست.');
        }

        $comment = BlogComment::create([
            'post_id' => $post->id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'status' => 'pending'
        ]);

        return redirect()->back()->with('success', 'نظر شما با موفقیت ثبت شد و پس از تایید نمایش داده خواهد شد.');
    }

    /**
     * Search posts
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        $posts = Post::with(['category', 'author', 'tags'])
                    ->published()
                    ->where(function($q) use ($query) {
                        $q->where('title', 'like', '%' . $query . '%')
                          ->orWhere('content', 'like', '%' . $query . '%')
                          ->orWhere('excerpt', 'like', '%' . $query . '%');
                    })
                    ->orderBy('published_at', 'desc')
                    ->paginate(12);

        $categories = BlogCategory::active()->ordered()->get();
        $popularPosts = Post::published()->popular(5)->get();
        $tags = BlogTag::has('posts')->get();

        return view('Blog::frontend.search', array_merge(
            compact('posts', 'query', 'categories', 'popularPosts', 'tags'),
            $this->metadata(
                'جستجو در بلاگ ارث‌کوپ',
                'نتایج جستجو در مقاله‌های بلاگ ارث‌کوپ.',
                '/blog/search',
                robots: 'noindex,follow',
            ),
        ));
    }

    private function metadata(
        string $title,
        string $description,
        string $path,
        string $robots = 'index,follow',
        string $type = 'website',
        ?string $image = null,
        array $jsonLd = [],
    ): array {
        return [
            'seoTitle' => $title,
            'seoDescription' => $description,
            'seoCanonical' => $this->canonicalUrl->to($path),
            'seoRobots' => $robots,
            'seoType' => $type,
            'seoImage' => $image,
            'seoJsonLd' => $jsonLd,
        ];
    }
}
