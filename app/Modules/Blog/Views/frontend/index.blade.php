@extends('layouts.unified')

@section('title', 'بلاگ - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Blog Page Styles */
    .blog-hero {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
        padding: 4rem 0;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .blog-hero::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 100%;
        background: url('https://images.unsplash.com/photo-1516321497487-e288ad716477?ixlib=rb-4.0.3&auto=format&fit=crop&w=1800&q=80') center/cover;
        opacity: 0.1;
        z-index: 0;
    }

    .blog-hero-content {
        position: relative;
        z-index: 1;
    }

    .blog-hero h1 {
        font-size: 3rem;
        font-weight: 800;
        color: var(--color-gentle-black);
        margin-bottom: 1.5rem;
    }

    .blog-hero p {
        font-size: 1.25rem;
        color: #4b5563;
        max-width: 600px;
        margin: 0 auto 2rem;
    }

    /* Blog Card Styles */
    .blog-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(220, 220, 220, 0.3);
        height: 100%;
    }

    .blog-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
    }

    .blog-card img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .blog-card-content {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .blog-card-title {
        font-size: 1.5rem;
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

    .blog-card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .blog-card-description {
        font-size: 1rem;
        color: #4b5563;
        margin-bottom: 1.5rem;
        flex-grow: 1;
    }

    .blog-card-link {
        display: inline-flex;
        align-items: center;
        color: var(--color-earth-green);
        font-weight: 500;
        text-decoration: none;
        transition: color 0.3s ease;
        margin-top: auto;
    }

    .blog-card-link:hover {
        color: var(--color-dark-green);
    }

    .blog-card-link i {
        margin-right: 0.5rem;
        transition: transform 0.3s ease;
    }

    .blog-card-link:hover i {
        transform: translateX(-5px);
    }

    .blog-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .blog-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .blog-tag {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #f3f4f6;
        color: #6b7280;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .blog-tag:hover {
        background: var(--color-earth-green);
        color: white;
    }

    /* Search & Filter Section */
    .search-filter-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .search-filter-form {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .search-input {
        flex: 1;
        min-width: 200px;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 1rem;
        transition: border-color 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--color-earth-green);
    }

    .filter-select {
        flex: 1;
        min-width: 200px;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 1rem;
        background: white;
        cursor: pointer;
        transition: border-color 0.3s ease;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--color-earth-green);
    }

    .search-btn {
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .search-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    /* Sidebar Styles */
    .sidebar-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .sidebar-header {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .sidebar-body {
        padding: 1.5rem;
    }

    .category-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .category-item {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color 0.2s ease;
    }

    .category-item:last-child {
        border-bottom: none;
    }

    .category-item:hover {
        background-color: #f9fafb;
        padding-right: 0.5rem;
    }

    .category-item a {
        color: var(--color-gentle-black);
        text-decoration: none;
        flex-grow: 1;
        transition: color 0.2s ease;
    }

    .category-item a:hover {
        color: var(--color-earth-green);
    }

    .category-badge {
        background: var(--color-earth-green);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .popular-post-item {
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
        transition: background-color 0.2s ease;
    }

    .popular-post-item:last-child {
        border-bottom: none;
    }

    .popular-post-item:hover {
        background-color: #f9fafb;
        padding-right: 0.5rem;
    }

    .popular-post-item a {
        color: var(--color-gentle-black);
        text-decoration: none;
        display: block;
    }

    .popular-post-item a:hover {
        color: var(--color-earth-green);
    }

    .popular-post-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    .popular-post-meta {
        font-size: 0.875rem;
        color: #6b7280;
    }

    .tags-cloud {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .tag-cloud-item {
        padding: 0.5rem 1rem;
        background: #f3f4f6;
        color: #6b7280;
        border-radius: 0.5rem;
        text-decoration: none;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .tag-cloud-item:hover {
        background: var(--color-earth-green);
        color: white;
        border-color: var(--color-earth-green);
        transform: translateY(-2px);
    }

    /* Featured Posts Section */
    .featured-posts-section {
        margin-bottom: 3rem;
    }

    .section-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-gentle-black);
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .section-separator {
        width: 100px;
        height: 5px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
        border-radius: 5px;
        margin: 0 auto 2rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .empty-state i {
        font-size: 4rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }

    .empty-state p {
        font-size: 1.25rem;
        color: #6b7280;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 3rem;
    }

    /* Responsive */
    @media screen and (max-width: 768px) {
        .blog-hero h1 {
            font-size: 2rem;
        }

        .blog-hero p {
            font-size: 1rem;
        }

        .search-filter-form {
            flex-direction: column;
        }

        .search-input,
        .filter-select,
        .search-btn {
            width: 100%;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .blog-card,
        .search-filter-card,
        .sidebar-card {
            background: #1f2937;
            color: #f9fafb;
        }

        .blog-card-title,
        .category-item a,
        .popular-post-item a {
            color: #f9fafb;
        }

        .blog-card-description {
            color: #d1d5db;
        }

        .search-input,
        .filter-select {
            background: #374151;
            border-color: #4b5563;
            color: #f9fafb;
        }
    }
</style>
@endpush

@section('content')
<!-- Blog Hero Section -->
<section class="blog-hero">
    <div class="blog-hero-content">
        <div class="container mx-auto px-6">
            <h1>بلاگ EarthCoop</h1>
            <p>جدیدترین اخبار، مقالات و بینش‌ها را از جامعه جهانی ما کشف کنید.</p>
        </div>
    </div>
</section>

        <!-- Main Content -->
<div class="container mx-auto px-6 py-8" style="direction: rtl;">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Content Area -->
        <div class="lg:col-span-3">
            <!-- Featured Posts -->
            @if($featuredPosts->count() > 0)
            <div class="featured-posts-section">
                <h2 class="section-title">مقالات ویژه</h2>
                <div class="section-separator"></div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($featuredPosts as $post)
                    <div class="blog-card">
                            @if($post->featured_image)
                        <img src="{{ asset('images/blog/posts/' . $post->featured_image) }}" alt="{{ $post->title }}">
                        @else
                        <div style="height: 200px; background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-ocean-blue) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                            @endif
                        <div class="blog-card-content">
                            <span class="blog-badge">{{ $post->category->name }}</span>
                            <h3 class="blog-card-title">
                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                            </h3>
                            <p class="blog-card-description">{{ Str::limit($post->excerpt, 100) }}</p>
                            <div class="blog-card-meta">
                                <span><i class="fas fa-calendar-alt ml-2"></i> {{ $post->published_at->format('Y/m/d') }}</span>
                                <span><i class="fas fa-eye ml-2"></i> {{ $post->views_count }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Search & Filter -->
            <div class="search-filter-card">
                <form action="{{ route('blog.index') }}" method="GET" class="search-filter-form">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="جستجو در مقالات..." 
                           value="{{ request('search') }}">
                    <select name="category" class="filter-select">
                                <option value="">همه دسته‌بندی‌ها</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                                @endforeach
                            </select>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search ml-2"></i>
                        جستجو
                            </button>
                    </form>
            </div>

            <!-- Blog Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @forelse($posts as $post)
                <div class="blog-card">
                        @if($post->featured_image)
                    <img src="{{ asset('images/blog/posts/' . $post->featured_image) }}" alt="{{ $post->title }}">
                    @else
                    <div style="height: 200px; background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-ocean-blue) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                        <i class="fas fa-newspaper"></i>
                    </div>
                        @endif
                    <div class="blog-card-content">
                        <span class="blog-badge">{{ $post->category->name }}</span>
                        <h3 class="blog-card-title">
                            <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                        </h3>
                        <p class="blog-card-description">{{ Str::limit($post->excerpt, 150) }}</p>
                            
                            <!-- Tags -->
                            @if($post->tags->count() > 0)
                        <div class="blog-tags">
                                @foreach($post->tags as $tag)
                            <a href="{{ route('blog.tag', $tag->slug) }}" class="blog-tag">
                                    #{{ $tag->name }}
                                </a>
                                @endforeach
                            </div>
                            @endif

                        <div class="blog-card-meta">
                            <span><i class="fas fa-user ml-2"></i> {{ $post->author->name }}</span>
                            <span>
                                <i class="fas fa-calendar-alt ml-2"></i> {{ $post->published_at->format('Y/m/d') }}
                                <i class="fas fa-eye mr-2"></i> {{ $post->views_count }}
                            </span>
                        </div>

                        <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-link">
                            ادامه مطلب <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                </div>
                @empty
                <div class="col-span-2">
                    <div class="empty-state">
                        <i class="fas fa-newspaper"></i>
                        <p>هیچ مقاله‌ای یافت نشد.</p>
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
            <!-- Categories -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fas fa-folder ml-2"></i>
                    دسته‌بندی‌ها
                </div>
                <div class="sidebar-body">
                    <ul class="category-list">
                    @foreach($categories as $category)
                        <li class="category-item">
                            <a href="{{ route('blog.category', $category->slug) }}">
                        {{ $category->name }}
                    </a>
                            <span class="category-badge">{{ $category->publishedPostsCount() }}</span>
                        </li>
                    @endforeach
                    </ul>
                </div>
            </div>

            <!-- Popular Posts -->
            @if($popularPosts->count() > 0)
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fas fa-fire ml-2"></i>
                    محبوب‌ترین مقالات
                </div>
                <div class="sidebar-body">
                        @foreach($popularPosts as $post)
                    <div class="popular-post-item">
                        <a href="{{ route('blog.show', $post->slug) }}">
                            <div class="popular-post-title">{{ Str::limit($post->title, 50) }}</div>
                            <div class="popular-post-meta">
                                <i class="fas fa-eye ml-2"></i> {{ $post->views_count }} بازدید
                            </div>
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Tags Cloud -->
            @if($tags->count() > 0)
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fas fa-tags ml-2"></i>
                    برچسب‌ها
                </div>
                <div class="sidebar-body">
                    <div class="tags-cloud">
                    @foreach($tags as $tag)
                        <a href="{{ route('blog.tag', $tag->slug) }}" class="tag-cloud-item">
                        #{{ $tag->name }}
                    </a>
                    @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
