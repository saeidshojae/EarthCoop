@extends('layouts.unified')

@section('title', 'دسته‌بندی: ' . $category->name . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
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

    /* Category Header */
    .category-header {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
        border-radius: 1rem;
        padding: 2.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 2rem;
    }

    .category-image {
        width: 120px;
        height: 120px;
        border-radius: 1rem;
        object-fit: cover;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .category-info h1 {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--color-gentle-black);
        margin-bottom: 0.75rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .category-info p {
        font-size: 1.1rem;
        color: #64748b;
        margin-bottom: 1rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .category-badge {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: white;
        padding: 0.5rem 1.25rem;
        border-radius: 0.5rem;
        font-size: 1rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Blog Card */
    .blog-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        overflow: hidden;
        border: 1px solid rgba(220, 220, 220, 0.3);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .blog-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .blog-card img {
        width: 100%;
        height: 250px;
        object-fit: cover;
    }

    .blog-card-content {
        padding: 1.5rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .blog-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .blog-badge {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: white;
        padding: 0.4rem 0.9rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
    }

    .blog-card-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.75rem;
        line-height: 1.4;
    }

    .blog-card-title a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .blog-card-title a:hover {
        color: var(--color-earth-green);
    }

    .blog-card-description {
        color: #64748b;
        line-height: 1.7;
        margin-bottom: 1rem;
        flex-grow: 1;
    }

    .blog-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .blog-tag {
        background: #f1f5f9;
        color: var(--color-gentle-black);
        padding: 0.35rem 0.75rem;
        border-radius: 0.375rem;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .blog-tag:hover {
        background: var(--color-earth-green);
        color: white;
    }

    .blog-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
        font-size: 0.85rem;
        color: #64748b;
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

    .category-item {
        padding: 0.75rem;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--color-gentle-black);
    }

    .category-item:hover {
        background: #f1f5f9;
        transform: translateX(-5px);
    }

    .category-item.active {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));
        border-right: 3px solid var(--color-earth-green);
    }

    .category-count {
        background: var(--color-ocean-blue);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .category-item.active .category-count {
        background: var(--color-earth-green);
    }

    .popular-post-item {
        padding: 0.75rem;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: block;
        color: var(--color-gentle-black);
    }

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

    .tag-cloud {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .tag-cloud-item {
        background: #f1f5f9;
        color: var(--color-gentle-black);
        padding: 0.4rem 0.8rem;
        border-radius: 0.375rem;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .tag-cloud-item:hover {
        background: var(--color-earth-green);
        color: white;
    }

    .back-btn {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        width: 100%;
        justify-content: center;
    }

    .back-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 2px dashed #cbd5e1;
    }

    .empty-state-icon {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.5rem;
    }

    .empty-state-message {
        color: #64748b;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 3rem;
    }

    .pagination-wrapper .pagination {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 0.5rem;
        background: var(--color-light-gray);
        padding: 0.75rem;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .pagination-wrapper .pagination li a,
    .pagination-wrapper .pagination li span {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        text-decoration: none;
        transition: all 0.3s ease;
        min-width: 40px;
        font-family: 'Vazirmatn', sans-serif;
    }

    .pagination-wrapper .pagination li a:hover {
        background: var(--color-earth-green);
        color: var(--color-pure-white);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
    }

    .pagination-wrapper .pagination li.active span {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: var(--color-pure-white);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    @media (max-width: 768px) {
        .category-header {
            flex-direction: column;
            text-align: center;
        }

        .category-info h1 {
            font-size: 1.75rem;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-6 py-8" style="direction: rtl;">
    <!-- Category Header -->
    <div class="category-header">
        @if($category->image)
        <img src="{{ asset('images/blog/categories/' . $category->image) }}" 
             alt="{{ $category->name }}" 
             class="category-image">
        @endif
        <div class="category-info" style="flex: 1;">
            <h1>{{ $category->name }}</h1>
            @if($category->description)
            <p>{{ $category->description }}</p>
            @endif
            <div class="category-badge">
                <i class="fas fa-file-alt"></i>
                {{ $posts->total() }} مقاله
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Blog Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @forelse($posts as $post)
                <div class="blog-card">
                    @if($post->featured_image)
                    <img src="{{ asset('images/blog/posts/' . $post->featured_image) }}" 
                         alt="{{ $post->title }}">
                    @else
                    <div style="height: 250px; background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-ocean-blue) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    @endif
                    <div class="blog-card-content">
                        <div class="blog-card-header">
                            <a href="{{ route('blog.category', $post->category->slug) }}" class="blog-badge">
                                {{ $post->category->name }}
                            </a>
                            <small style="color: #64748b;">
                                <i class="far fa-clock" style="margin-left: 0.25rem;"></i>
                                {{ $post->reading_time }} دقیقه
                            </small>
                        </div>
                        <h3 class="blog-card-title">
                            <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                        </h3>
                        <p class="blog-card-description">{{ Str::limit($post->excerpt, 150) }}</p>
                        
                        <!-- Tags -->
                        @if($post->tags->count() > 0)
                        <div class="blog-tags">
                            @foreach($post->tags->take(3) as $tag)
                            <a href="{{ route('blog.tag', $tag->slug) }}" class="blog-tag">
                                #{{ $tag->name }}
                            </a>
                            @endforeach
                        </div>
                        @endif

                        <div class="blog-card-footer">
                            <small>
                                <i class="far fa-user" style="margin-left: 0.25rem;"></i>
                                {{ $post->author->name }}
                            </small>
                            <small>
                                <i class="far fa-calendar" style="margin-left: 0.25rem;"></i>
                                {{ $post->published_at->format('Y/m/d') }}
                                <i class="far fa-eye" style="margin: 0 0.5rem;"></i>
                                {{ $post->views_count }}
                            </small>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-span-2">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h2 class="empty-state-title">هیچ مقاله‌ای یافت نشد</h2>
                        <p class="empty-state-message">هیچ مقاله‌ای در این دسته‌بندی یافت نشد.</p>
                    </div>
                </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if($posts->hasPages())
            <div class="pagination-wrapper">
                {{ $posts->links() }}
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- All Categories -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i class="fas fa-folder" style="color: var(--color-earth-green);"></i>
                    تمام دسته‌بندی‌ها
                </h3>
                @foreach($categories as $cat)
                <a href="{{ route('blog.category', $cat->slug) }}" 
                   class="category-item {{ $cat->id == $category->id ? 'active' : '' }}">
                    <span>{{ $cat->name }}</span>
                    <span class="category-count">{{ $cat->publishedPostsCount() }}</span>
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
                @foreach($popularPosts as $post)
                <a href="{{ route('blog.show', $post->slug) }}" class="popular-post-item">
                    <h6>{{ Str::limit($post->title, 50) }}</h6>
                    <small>
                        <i class="far fa-eye" style="margin-left: 0.25rem;"></i>
                        {{ $post->views_count }} بازدید
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
                <div class="tag-cloud">
                    @foreach($tags as $tag)
                    <a href="{{ route('blog.tag', $tag->slug) }}" class="tag-cloud-item">
                        #{{ $tag->name }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Back to Blog -->
            <a href="{{ route('blog.index') }}" class="back-btn">
                <i class="fas fa-arrow-right"></i>
                بازگشت به وبلاگ
            </a>
        </div>
    </div>
</div>
@endsection
