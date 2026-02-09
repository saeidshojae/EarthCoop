@extends('layouts.unified')

@section('title', ($post->meta_title ?? $post->title) . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
<meta name="description" content="{{ $post->meta_description ?? $post->excerpt }}">
<meta name="keywords" content="{{ $post->meta_keywords }}">

<style>
    :root {
        --color-earth-green: #10b981;
        --color-ocean-blue: #3b82f6;
        --color-digital-gold: #f59e0b;
        --color-pure-white: #ffffff;
        --color-light-gray: #f8fafc;
        --color-gentle-black: #1e293b;
        --color-dark-green: #047857;
        --color-dark-blue: #1d4ed8;
    }

    .font-vazirmatn { font-family: 'Vazirmatn', sans-serif; }

    /* Post Header */
    .post-header {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
        padding: 3rem 0;
        margin-bottom: 2rem;
        border-radius: 1rem;
    }

    .post-featured-image {
        width: 100%;
        max-height: 500px;
        object-fit: cover;
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    /* Post Card */
    .post-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        padding: 2.5rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(220, 220, 220, 0.3);
    }

    .post-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: center;
    }

    .post-badge {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .post-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
        font-size: 0.9rem;
    }

    .post-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--color-gentle-black);
        margin-bottom: 1.5rem;
        line-height: 1.3;
        font-family: 'Vazirmatn', sans-serif;
    }

    .post-author {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem 0;
        border-top: 2px solid #e2e8f0;
        border-bottom: 2px solid #e2e8f0;
        margin: 2rem 0;
    }

    .post-author-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .post-content {
        font-size: 1.125rem;
        line-height: 1.9;
        color: var(--color-gentle-black);
        margin: 2rem 0;
        font-family: 'Vazirmatn', sans-serif;
    }

    .post-content h1,
    .post-content h2,
    .post-content h3 {
        color: var(--color-gentle-black);
        margin-top: 2rem;
        margin-bottom: 1rem;
        font-weight: 700;
    }

    .post-content p {
        margin-bottom: 1.5rem;
    }

    .post-content img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        margin: 1.5rem 0;
    }

    .post-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid #e2e8f0;
    }

    .post-tag {
        background: #f1f5f9;
        color: var(--color-gentle-black);
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .post-tag:hover {
        background: var(--color-earth-green);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
    }

    /* Related Posts */
    .related-posts-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(220, 220, 220, 0.3);
    }

    .section-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .related-post-card {
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 0.75rem;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid rgba(220, 220, 220, 0.3);
        height: 100%;
    }

    .related-post-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .related-post-card img {
        width: 100%;
        height: 180px;
        object-fit: cover;
    }

    .related-post-content {
        padding: 1.25rem;
    }

    .related-post-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.75rem;
        line-height: 1.4;
    }

    .related-post-title a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .related-post-title a:hover {
        color: var(--color-earth-green);
    }

    .related-post-meta {
        font-size: 0.85rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Comments Section */
    .comments-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        padding: 2rem;
        border: 1px solid rgba(220, 220, 220, 0.3);
    }

    .comment-form {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 0.75rem;
        margin-bottom: 2rem;
    }

    .comment-textarea {
        width: 100%;
        padding: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        font-size: 1rem;
        font-family: 'Vazirmatn', sans-serif;
        resize: vertical;
        min-height: 120px;
    }

    .comment-textarea:focus {
        outline: none;
        border-color: var(--color-earth-green);
    }

    .comment-submit-btn {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: white;
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 0.5rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .comment-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .comment-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }

    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .comment-author {
        font-weight: 700;
        color: var(--color-gentle-black);
        font-size: 1.1rem;
    }

    .comment-time {
        font-size: 0.85rem;
        color: #64748b;
    }

    .comment-content {
        color: var(--color-gentle-black);
        line-height: 1.7;
        font-size: 1rem;
    }

    .comment-reply {
        margin-right: 2rem;
        margin-top: 1rem;
        padding-right: 1.5rem;
        border-right: 3px solid var(--color-earth-green);
    }

    /* Sidebar */
    .sidebar-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(220, 220, 220, 0.3);
    }

    .sidebar-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .category-item,
    .popular-post-item {
        padding: 0.75rem;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: block;
        color: var(--color-gentle-black);
    }

    .category-item:hover,
    .popular-post-item:hover {
        background: #f1f5f9;
        transform: translateX(-5px);
    }

    .popular-post-item h6 {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--color-gentle-black);
    }

    .popular-post-item small {
        color: #64748b;
        font-size: 0.85rem;
    }

    @media (max-width: 768px) {
        .post-title {
            font-size: 1.75rem;
        }

        .post-card {
            padding: 1.5rem;
        }

        .post-meta {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-6 py-8" style="direction: rtl;">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Post Header -->
                @if($post->featured_image)
                <img src="{{ asset('images/blog/posts/' . $post->featured_image) }}" 
                     alt="{{ $post->title }}"
                 class="post-featured-image">
                @endif
                
            <!-- Post Card -->
            <div class="post-card">
                <!-- Meta Information -->
                <div class="post-meta">
                    <a href="{{ route('blog.category', $post->category->slug) }}" class="post-badge">
                        <i class="fas fa-folder"></i>
                            {{ $post->category->name }}
                        </a>
                    <div class="post-meta-item">
                        <i class="far fa-calendar"></i>
                        <span>{{ $post->published_at->format('Y/m/d') }}</span>
                    </div>
                    <div class="post-meta-item">
                        <i class="far fa-eye"></i>
                        <span>{{ $post->views_count }} بازدید</span>
                    </div>
                    <div class="post-meta-item">
                        <i class="far fa-clock"></i>
                        <span>{{ $post->reading_time }} دقیقه مطالعه</span>
                    </div>
                    </div>

                    <!-- Title -->
                <h1 class="post-title">{{ $post->title }}</h1>

                    <!-- Author -->
                <div class="post-author">
                    <div class="post-author-icon">
                        <i class="fas fa-user"></i>
                    </div>
                        <div>
                        <div style="font-weight: 700; color: var(--color-gentle-black);">نویسنده:</div>
                        <div style="color: #64748b;">{{ $post->author->name }}</div>
                    </div>
                    </div>

                <!-- Content -->
                <div class="post-content">
                    {!! $post->content !!}
                </div>

                    <!-- Tags -->
                    @if($post->tags->count() > 0)
                <div class="post-tags">
                    <strong style="color: var(--color-gentle-black); margin-left: 0.5rem;">برچسب‌ها:</strong>
                        @foreach($post->tags as $tag)
                    <a href="{{ route('blog.tag', $tag->slug) }}" class="post-tag">
                            #{{ $tag->name }}
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>

            <!-- Related Posts -->
            @if($relatedPosts->count() > 0)
            <div class="related-posts-card">
                <h2 class="section-title">
                    <i class="fas fa-newspaper" style="color: var(--color-earth-green);"></i>
                    مقالات مرتبط
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($relatedPosts as $relatedPost)
                    <div class="related-post-card">
                                @if($relatedPost->featured_image)
                                <img src="{{ asset('images/blog/posts/' . $relatedPost->featured_image) }}" 
                             alt="{{ $relatedPost->title }}">
                        @else
                        <div style="height: 180px; background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-ocean-blue) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                                @endif
                        <div class="related-post-content">
                            <h3 class="related-post-title">
                                <a href="{{ route('blog.show', $relatedPost->slug) }}">
                                            {{ $relatedPost->title }}
                                        </a>
                            </h3>
                            <div class="related-post-meta">
                                <i class="far fa-calendar"></i>
                                <span>{{ $relatedPost->published_at->format('Y/m/d') }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Comments Section -->
            @if($post->allow_comments)
            <div class="comments-card">
                <h2 class="section-title">
                    <i class="fas fa-comments" style="color: var(--color-earth-green);"></i>
                    نظرات
                    <span style="background: var(--color-ocean-blue); color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 1rem; margin-right: 0.5rem;">
                        {{ $post->approvedComments->count() }}
                    </span>
                </h2>

                    <!-- Add Comment Form -->
                    @auth
                <form action="{{ route('blog.comment.store', $post) }}" method="POST" class="comment-form">
                        @csrf
                    <label for="content" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--color-gentle-black);">نظر شما</label>
                    <textarea name="content" id="content" class="comment-textarea" required></textarea>
                    <button type="submit" class="comment-submit-btn" style="margin-top: 1rem;">
                        <i class="fas fa-paper-plane"></i>
                        ارسال نظر
                        </button>
                    </form>
                    @else
                <div style="background: #dbeafe; border: 1px solid #93c5fd; border-radius: 0.75rem; padding: 1rem; margin-bottom: 2rem; color: #1e40af;">
                    <i class="fas fa-info-circle" style="margin-left: 0.5rem;"></i>
                    برای ثبت نظر، لطفاً <a href="{{ route('login') }}" style="color: #1e40af; font-weight: 600; text-decoration: underline;">وارد شوید</a>.
                    </div>
                    @endauth

                    <!-- Comments List -->
                    @foreach($post->approvedComments as $comment)
                <div class="comment-item">
                    <div class="comment-header">
                        <div class="comment-author">{{ $comment->user->name }}</div>
                        <div class="comment-time">{{ $comment->created_at->diffForHumans() }}</div>
                            </div>
                    <div class="comment-content">{{ $comment->content }}</div>

                            <!-- Replies -->
                            @if($comment->replies->count() > 0)
                    <div style="margin-top: 1rem;">
                                @foreach($comment->replies as $reply)
                        <div class="comment-reply">
                            <div class="comment-header">
                                <div class="comment-author" style="font-size: 0.95rem;">{{ $reply->user->name }}</div>
                                <div class="comment-time">{{ $reply->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="comment-content" style="font-size: 0.95rem;">{{ $reply->content }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Categories -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-folder" style="color: var(--color-earth-green);"></i>
                    دسته‌بندی‌ها
                </h3>
                    @foreach($categories as $category)
                <a href="{{ route('blog.category', $category->slug) }}" class="category-item">
                        {{ $category->name }}
                    </a>
                    @endforeach
            </div>

            <!-- Popular Posts -->
            @if($popularPosts->count() > 0)
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-fire" style="color: var(--color-digital-gold);"></i>
                    محبوب‌ترین مقالات
                </h3>
                        @foreach($popularPosts as $popularPost)
                <a href="{{ route('blog.show', $popularPost->slug) }}" class="popular-post-item">
                    <h6>{{ Str::limit($popularPost->title, 50) }}</h6>
                    <small>
                        <i class="far fa-eye" style="margin-left: 0.25rem;"></i>
                        {{ $popularPost->views_count }} بازدید
                            </small>
                        </a>
                        @endforeach
                    </div>
            @endif

            <!-- Tags Cloud -->
            @if($tags->count() > 0)
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-tags" style="color: var(--color-ocean-blue);"></i>
                    برچسب‌ها
                </h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach($tags as $tag)
                    <a href="{{ route('blog.tag', $tag->slug) }}" class="post-tag" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                        #{{ $tag->name }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
