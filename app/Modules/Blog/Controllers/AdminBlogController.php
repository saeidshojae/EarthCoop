<?php

namespace App\Modules\Blog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Models\BlogComment;
use App\Modules\Blog\Requests\PostRequest;
use App\Modules\Blog\Requests\CategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBlogController extends Controller
{
    /**
     * Display admin dashboard
     */
    public function dashboard()
    {
        $totalPosts = Post::count();
        $publishedPosts = Post::where('status', 'published')->count();
        $draftPosts = Post::where('status', 'draft')->count();
        $totalComments = BlogComment::count();
        $pendingComments = BlogComment::where('status', 'pending')->count();
        $totalCategories = BlogCategory::count();
        $totalTags = BlogTag::count();

        $recentPosts = Post::with(['category', 'author'])->latest()->take(10)->get();
        $recentComments = BlogComment::with(['post', 'user'])->latest()->take(10)->get();

        return view('Blog::admin.dashboard', compact(
            'totalPosts', 'publishedPosts', 'draftPosts', 'totalComments', 
            'pendingComments', 'totalCategories', 'totalTags', 'recentPosts', 'recentComments'
        ));
    }

    // ==================== POSTS ====================

    /**
     * Display posts list
     */
    public function posts(Request $request)
    {
        $query = Post::with(['category', 'author']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $posts = $query->latest()->paginate(20);
        $categories = BlogCategory::all();

        return view('Blog::admin.posts.index', compact('posts', 'categories'));
    }

    /**
     * Show create post form
     */
    public function createPost()
    {
        $categories = BlogCategory::active()->get();
        $tags = BlogTag::all();
        return view('Blog::admin.posts.create', compact('categories', 'tags'));
    }

    /**
     * Store new post
     */
    public function storePost(PostRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Handle featured image
        if ($request->hasFile('featured_image')) {
            $image = $request->file('featured_image');
            $imageName = time() . '_' . Str::slug($data['title']) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/blog/posts'), $imageName);
            $data['featured_image'] = $imageName;
        }

        // Auto-set published_at for published posts
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $post = Post::create($data);

        // Sync tags
        if ($request->has('tags')) {
            $post->tags()->sync($request->tags);
        }

        return redirect()->route('admin.blog.posts')->with('success', 'مقاله با موفقیت ایجاد شد.');
    }

    /**
     * Show edit post form
     */
    public function editPost(Post $post)
    {
        $categories = BlogCategory::active()->get();
        $tags = BlogTag::all();
        return view('Blog::admin.posts.edit', compact('post', 'categories', 'tags'));
    }

    /**
     * Update post
     */
    public function updatePost(PostRequest $request, Post $post)
    {
        $data = $request->validated();

        // Handle featured image
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($post->featured_image && file_exists(public_path('images/blog/posts/' . $post->featured_image))) {
                unlink(public_path('images/blog/posts/' . $post->featured_image));
            }

            $image = $request->file('featured_image');
            $imageName = time() . '_' . Str::slug($data['title']) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/blog/posts'), $imageName);
            $data['featured_image'] = $imageName;
        }

        // Auto-set published_at for published posts
        if ($data['status'] === 'published' && empty($post->published_at) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $post->update($data);

        // Sync tags
        if ($request->has('tags')) {
            $post->tags()->sync($request->tags);
        }

        return redirect()->route('admin.blog.posts')->with('success', 'مقاله با موفقیت ویرایش شد.');
    }

    /**
     * Delete post
     */
    public function deletePost(Post $post)
    {
        // Delete featured image
        if ($post->featured_image && file_exists(public_path('images/blog/posts/' . $post->featured_image))) {
            unlink(public_path('images/blog/posts/' . $post->featured_image));
        }

        $post->delete();

        return redirect()->route('admin.blog.posts')->with('success', 'مقاله با موفقیت حذف شد.');
    }

    // ==================== CATEGORIES ====================

    /**
     * Display categories list
     */
    public function categories()
    {
        $categories = BlogCategory::withCount('posts')->orderBy('order')->get();
        return view('Blog::admin.categories.index', compact('categories'));
    }

    /**
     * Show create category form
     */
    public function createCategory()
    {
        return view('Blog::admin.categories.create');
    }

    /**
     * Store new category
     */
    public function storeCategory(CategoryRequest $request)
    {
        $data = $request->validated();

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Handle image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name']) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/blog/categories'), $imageName);
            $data['image'] = $imageName;
        }

        BlogCategory::create($data);

        return redirect()->route('admin.blog.categories')->with('success', 'دسته‌بندی با موفقیت ایجاد شد.');
    }

    /**
     * Show edit category form
     */
    public function editCategory(BlogCategory $category)
    {
        return view('Blog::admin.categories.edit', compact('category'));
    }

    /**
     * Update category
     */
    public function updateCategory(CategoryRequest $request, BlogCategory $category)
    {
        $data = $request->validated();

        // Handle image
        if ($request->hasFile('image')) {
            // Delete old image
            if ($category->image && file_exists(public_path('images/blog/categories/' . $category->image))) {
                unlink(public_path('images/blog/categories/' . $category->image));
            }

            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($data['name']) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/blog/categories'), $imageName);
            $data['image'] = $imageName;
        }

        $category->update($data);

        return redirect()->route('admin.blog.categories')->with('success', 'دسته‌بندی با موفقیت ویرایش شد.');
    }

    /**
     * Delete category
     */
    public function deleteCategory(BlogCategory $category)
    {
        // Check if category has posts
        if ($category->posts()->count() > 0) {
            return redirect()->back()->with('error', 'این دسته‌بندی دارای مقاله است و قابل حذف نیست.');
        }

        // Delete image
        if ($category->image && file_exists(public_path('images/blog/categories/' . $category->image))) {
            unlink(public_path('images/blog/categories/' . $category->image));
        }

        $category->delete();

        return redirect()->route('admin.blog.categories')->with('success', 'دسته‌بندی با موفقیت حذف شد.');
    }

    // ==================== TAGS ====================

    /**
     * Display tags list
     */
    public function tags()
    {
        $tags = BlogTag::withCount('posts')->get();
        return view('Blog::admin.tags.index', compact('tags'));
    }

    /**
     * Store new tag
     */
    public function storeTag(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:blog_tags,name'
        ]);

        BlogTag::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name)
        ]);

        return redirect()->route('admin.blog.tags')->with('success', 'برچسب با موفقیت ایجاد شد.');
    }

    /**
     * Update tag
     */
    public function updateTag(Request $request, BlogTag $tag)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:blog_tags,name,' . $tag->id
        ]);

        $tag->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name)
        ]);

        return redirect()->route('admin.blog.tags')->with('success', 'برچسب با موفقیت ویرایش شد.');
    }

    /**
     * Delete tag
     */
    public function deleteTag(BlogTag $tag)
    {
        $tag->delete();
        return redirect()->route('admin.blog.tags')->with('success', 'برچسب با موفقیت حذف شد.');
    }

    // ==================== COMMENTS ====================

    /**
     * Display comments list
     */
    public function comments(Request $request)
    {
        $query = BlogComment::with(['post', 'user']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $comments = $query->latest()->paginate(20);

        return view('Blog::admin.comments.index', compact('comments'));
    }

    /**
     * Approve comment
     */
    public function approveComment(BlogComment $comment)
    {
        $comment->update(['status' => 'approved']);
        return redirect()->back()->with('success', 'نظر تایید شد.');
    }

    /**
     * Reject comment
     */
    public function rejectComment(BlogComment $comment)
    {
        $comment->update(['status' => 'rejected']);
        return redirect()->back()->with('success', 'نظر رد شد.');
    }

    /**
     * Delete comment
     */
    public function deleteComment(BlogComment $comment)
    {
        $comment->delete();
        return redirect()->back()->with('success', 'نظر حذف شد.');
    }
}
