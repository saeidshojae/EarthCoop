<?php

namespace App\Modules\Blog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Models\BlogComment;
use App\Modules\Blog\Requests\CommentRequest;
use Illuminate\Http\Request;

class BlogController extends Controller
{
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

        return view('Blog::frontend.index', compact('posts', 'categories', 'popularPosts', 'featuredPosts', 'tags'));
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

        $relatedPosts = Post::published()
                            ->where('category_id', $post->category_id)
                            ->where('id', '!=', $post->id)
                            ->recent(4)
                            ->get();

        $categories = BlogCategory::active()->ordered()->get();
        $popularPosts = Post::published()->popular(5)->get();
        $tags = BlogTag::has('posts')->get();

        return view('Blog::frontend.show', compact('post', 'relatedPosts', 'categories', 'popularPosts', 'tags'));
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

        return view('Blog::frontend.category', compact('category', 'posts', 'categories', 'popularPosts', 'tags'));
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

        return view('Blog::frontend.tag', compact('tag', 'posts', 'categories', 'popularPosts', 'tags'));
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

        return view('Blog::frontend.search', compact('posts', 'query', 'categories', 'popularPosts', 'tags'));
    }
}
