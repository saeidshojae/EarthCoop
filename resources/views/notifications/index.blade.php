@extends('layouts.unified')

@section('title', 'اعلان‌ها - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* ======================================== */
    /* CUSTOM COLORS                            */
    /* ======================================== */
    :root {
        --color-earth-green: #10b981;
        --color-ocean-blue: #3b82f6;
        --color-digital-gold: #f59e0b;
        --color-pure-white: #ffffff;
        --color-light-gray: #f8fafc;
        --color-gentle-black: #1e293b;
        --color-dark-green: #047857;
        --color-dark-blue: #1d4ed8;
        --color-accent-peach: #ff7e5f;
        --color-accent-sky: #6dd5ed;
        --color-red-tomato: #ef4444;
    }

    .font-vazirmatn { font-family: 'Vazirmatn', sans-serif; }
    .font-poppins { font-family: 'Poppins', sans-serif; }

    /* ======================================== */
    /* NOTIFICATION CARD                        */
    /* ======================================== */
    .notification-card {
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid rgba(220, 220, 220, 0.3);
        position: relative;
        overflow: hidden;
    }

    .notification-card::before {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, var(--color-earth-green), var(--color-ocean-blue));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .notification-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .notification-card:hover::before {
        opacity: 1;
    }

    .notification-card.unread {
        background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%);
        border-right: 4px solid var(--color-ocean-blue);
    }

    .notification-card.unread::before {
        opacity: 1;
    }

    /* ======================================== */
    /* NOTIFICATION WRAPPER - HORIZONTAL LAYOUT */
    /* ======================================== */
    .notification-wrapper {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        width: 100%;
    }

    .notification-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .notification-icon.info {
        background: rgba(59, 130, 246, 0.15);
        color: var(--color-ocean-blue);
    }

    .notification-icon.success {
        background: rgba(16, 185, 129, 0.15);
        color: var(--color-earth-green);
    }

    .notification-icon.warning {
        background: rgba(245, 158, 11, 0.15);
        color: var(--color-digital-gold);
    }

    .notification-icon.danger {
        background: rgba(239, 68, 68, 0.15);
        color: var(--color-red-tomato);
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-content-link {
        display: block;
        color: inherit;
        text-decoration: none;
        border-radius: 10px;
        padding: 0.2rem 0.35rem;
        margin: -0.2rem -0.35rem;
    }

    .notification-content-link:focus-visible {
        outline: 3px solid rgba(59, 130, 246, 0.25);
        outline-offset: 3px;
    }

    .notification-content-link .notification-title::after {
        content: '\f060';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        display: inline-block;
        margin-right: 0.45rem;
        color: var(--color-ocean-blue);
        font-size: 0.72rem;
        opacity: 0.65;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .notification-content-link:hover .notification-title::after {
        transform: translateX(-3px);
        opacity: 1;
    }

    .notification-category {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.15rem 0.6rem;
        border-radius: 20px;
        margin-bottom: 0.3rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notification-category.category-groups {
        background: rgba(59, 130, 246, 0.12);
        color: var(--color-ocean-blue);
    }

    .notification-category.category-elections {
        background: rgba(245, 158, 11, 0.12);
        color: var(--color-digital-gold);
    }

    .notification-category.category-chat {
        background: rgba(16, 185, 129, 0.12);
        color: var(--color-earth-green);
    }

    .notification-category.category-auction {
        background: rgba(239, 68, 68, 0.12);
        color: var(--color-red-tomato);
    }

    .notification-category.category-najm {
        background: rgba(139, 92, 246, 0.12);
        color: #7c3aed;
    }

    .notification-category.category-system {
        background: rgba(100, 116, 139, 0.12);
        color: #64748b;
    }

    .notification-category.category-other {
        background: rgba(148, 163, 184, 0.12);
        color: #94a3b8;
    }

    .notification-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.25rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notification-message {
        font-size: 0.9rem;
        color: #64748b;
        line-height: 1.5;
        margin-bottom: 0.4rem;
        font-family: 'Vazirmatn', sans-serif;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notification-time {
        font-size: 0.75rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    /* ======================================== */
    /* ACTIONS - ALWAYS HORIZONTAL              */
    /* ======================================== */
    .notification-actions {
        display: flex;
        gap: 0.4rem;
        flex-shrink: 0;
        align-items: center;
        margin-right: 0.5rem;
    }

    .btn-notification {
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-family: 'Vazirmatn', sans-serif;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .btn-notification.read {
        background: var(--color-ocean-blue);
        color: var(--color-pure-white);
    }

    .btn-notification.read:hover {
        background: var(--color-dark-blue);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
    }

    .btn-notification.delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-notification.delete:hover {
        background: #fecaca;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);
    }

    .btn-notification i {
        font-size: 0.7rem;
    }

    /* ======================================== */
    /* EMPTY STATE                              */
    /* ======================================== */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 16px;
        border: 2px dashed #cbd5e1;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
        color: var(--color-earth-green);
    }

    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .empty-state-message {
        font-size: 1rem;
        color: #64748b;
        font-family: 'Vazirmatn', sans-serif;
    }

    /* ======================================== */
    /* HEADER ACTIONS                           */
    /* ======================================== */
    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .notifications-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--color-gentle-black);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notifications-title-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-pure-white);
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .header-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .btn-header-action {
        padding: 0.5rem 1rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-family: 'Vazirmatn', sans-serif;
        white-space: nowrap;
    }

    .btn-header-action.primary {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: var(--color-pure-white);
    }

    .btn-header-action.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
    }

    .btn-header-action.secondary {
        background: #f1f5f9;
        color: var(--color-gentle-black);
        border: 1px solid #e2e8f0;
    }

    .btn-header-action.secondary:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }

    /* ======================================== */
    /* FILTERS                                  */
    /* ======================================== */
    .notifications-filters {
        margin-bottom: 2rem;
    }

    .filters-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        background: #f8fafc;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        font-family: 'Vazirmatn', sans-serif;
    }

    .filter-select {
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        font-size: 0.85rem;
        font-family: 'Vazirmatn', sans-serif;
        color: var(--color-gentle-black);
        cursor: pointer;
        outline: none;
    }

    .filter-select:focus {
        border-color: var(--color-earth-green);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .btn-filter-clear {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.75rem;
        border-radius: 8px;
        background: #fee2e2;
        color: #dc2626;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        font-family: 'Vazirmatn', sans-serif;
        transition: all 0.3s ease;
    }

    .btn-filter-clear:hover {
        background: #fecaca;
        transform: translateY(-1px);
    }

    /* ======================================== */
    /* BADGE                                    */
    /* ======================================== */
    .unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--color-ocean-blue);
        color: var(--color-pure-white);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0 0.3rem;
    }

    /* ======================================== */
    /* PAGINATION                               */
    /* ======================================== */
    .pagination-wrapper {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }

    .pagination-wrapper .pagination {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 0.4rem;
        background: var(--color-light-gray);
        padding: 0.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        flex-wrap: wrap;
        justify-content: center;
    }

    .pagination-wrapper .pagination li {
        margin: 0;
    }

    .pagination-wrapper .pagination li a,
    .pagination-wrapper .pagination li span {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        text-decoration: none;
        transition: all 0.3s ease;
        min-width: 36px;
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

    .pagination-wrapper .pagination li.disabled span {
        color: #cbd5e1;
        cursor: not-allowed;
    }

    /* ======================================== */
    /* FADE-IN ANIMATION                        */
    /* ======================================== */
    .fade-in-section {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .fade-in-section.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* ======================================== */
    /* RESPONSIVE - MOBILE FIRST                */
    /* ======================================== */
    @media (max-width: 768px) {
        .container {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }

        .notifications-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            padding-bottom: 1rem;
        }

        .notifications-title {
            font-size: 1.4rem;
            justify-content: center;
        }

        .notifications-title-icon {
            width: 38px;
            height: 38px;
            font-size: 1rem;
        }

        .header-actions {
            justify-content: center;
            width: 100%;
        }

        .btn-header-action {
            font-size: 0.75rem;
            padding: 0.4rem 0.75rem;
        }

        .btn-header-action span {
            font-size: 0.7rem;
        }

        .filters-form {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
            padding: 0.75rem;
        }

        .filter-group {
            flex-direction: column;
            align-items: stretch;
            gap: 0.25rem;
        }

        .filter-select {
            width: 100%;
            padding: 0.4rem 0.6rem;
            font-size: 0.8rem;
        }

        .filter-label {
            font-size: 0.75rem;
        }

        .btn-filter-clear {
            justify-content: center;
            font-size: 0.75rem;
            padding: 0.25rem 0.6rem;
        }

        /* NOTIFICATION CARD - MOBILE */
        .notification-card {
            padding: 0.85rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .notification-wrapper {
            gap: 0.6rem;
            flex-wrap: nowrap;
            align-items: stretch;
        }

        .notification-icon {
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
            border-radius: 10px;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-category {
            font-size: 0.6rem;
            padding: 0.1rem 0.5rem;
        }

        .notification-title {
            font-size: 0.85rem;
            margin-bottom: 0.15rem;
        }

        .notification-message {
            font-size: 0.8rem;
            line-height: 1.4;
            margin-bottom: 0.3rem;
            -webkit-line-clamp: 2;
        }

        .notification-time {
            font-size: 0.65rem;
            gap: 0.25rem;
        }

        .notification-time i {
            font-size: 0.55rem;
        }

        /* ACTIONS - FORCE HORIZONTAL ON MOBILE */
        .notification-actions {
            display: flex !important;
            flex-direction: row !important;
            gap: 0.3rem;
            flex-shrink: 0;
            align-items: center;
            margin-right: 0;
            margin-top: 0;
            flex-wrap: nowrap;
        }

        .btn-notification {
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            white-space: nowrap;
            gap: 0.15rem;
        }

        .btn-notification i {
            font-size: 0.55rem;
        }

        .btn-notification span {
            font-size: 0.6rem;
        }

        .empty-state {
            padding: 2.5rem 1rem;
        }

        .empty-state-icon {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }

        .empty-state-title {
            font-size: 1.2rem;
        }

        .empty-state-message {
            font-size: 0.9rem;
        }

        .pagination-wrapper .pagination {
            padding: 0.4rem;
            gap: 0.25rem;
        }

        .pagination-wrapper .pagination li a,
        .pagination-wrapper .pagination li span {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            min-width: 30px;
        }
    }

    @media (max-width: 400px) {
        .notification-card {
            padding: 0.65rem;
        }

        .notification-wrapper {
            gap: 0.4rem;
        }

        .notification-icon {
            width: 30px;
            height: 30px;
            font-size: 0.75rem;
            border-radius: 8px;
        }

        .notification-title {
            font-size: 0.75rem;
        }

        .notification-message {
            font-size: 0.7rem;
            -webkit-line-clamp: 2;
        }

        .notification-time {
            font-size: 0.55rem;
        }

        .notification-actions {
            gap: 0.2rem;
        }

        .btn-notification {
            font-size: 0.55rem;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }

        .btn-notification i {
            font-size: 0.45rem;
        }

        .btn-notification span {
            font-size: 0.5rem;
        }

        .header-actions {
            flex-wrap: wrap;
            gap: 0.3rem;
        }

        .btn-header-action {
            font-size: 0.65rem;
            padding: 0.3rem 0.6rem;
        }

        .btn-header-action i {
            font-size: 0.6rem;
        }

        .btn-header-action span {
            font-size: 0.6rem;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto flex flex-col lg:flex-row gap-8 p-4 md:p-8">
    @include('partials.sidebar-unified')

    <main class="flex-grow min-w-0">
        <!-- Header -->
        <div class="notifications-header">
            <h1 class="notifications-title">
                <div class="notifications-title-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <span>اعلان‌ها</span>
                @php
                    $unreadCount = auth()->user()->unreadNotifications->count();
                @endphp
                @if($unreadCount > 0)
                    <span class="unread-badge">{{ $unreadCount }}</span>
                @endif
            </h1>

            <div class="header-actions">
                <a href="{{ route('notifications.settings') }}" class="btn-header-action secondary">
                    <i class="fas fa-cog"></i>
                    <span>تنظیمات</span>
                </a>
                @php
                    $hasUnread = $notifications->filter(function($n) { return is_null($n->read_at); })->count() > 0;
                @endphp
                @if($hasUnread || $unreadCount > 0)
                    <form action="{{ route('notifications.readAll') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn-header-action primary">
                            <i class="fas fa-check-double"></i>
                            <span>خواندن همه</span>
                        </button>
                    </form>
                @endif

                @php
                    $hasRead = $notifications->filter(function($n) { return !is_null($n->read_at); })->count() > 0;
                @endphp
                @if($hasRead)
                    <form action="{{ route('notifications.clearRead') }}" method="POST" class="m-0" onsubmit="return confirm('آیا از حذف همه اعلان‌های خوانده شده اطمینان دارید؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-header-action secondary">
                            <i class="fas fa-trash-alt"></i>
                            <span>حذف خوانده‌ها</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Filters -->
        <div class="notifications-filters fade-in-section">
            <form method="GET" action="{{ route('notifications.index') }}" class="filters-form">
                <div class="filter-group">
                    <label for="status" class="filter-label">وضعیت:</label>
                    <select name="status" id="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>همه</option>
                        <option value="unread" {{ request('status') === 'unread' ? 'selected' : '' }}>نخوانده</option>
                        <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>خوانده شده</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="type" class="filter-label">دسته:</label>
                    <select name="type" id="type" class="filter-select" onchange="this.form.submit()">
                        <option value="all" {{ request('type') === 'all' || !request('type') ? 'selected' : '' }}>همه</option>
                        <option value="group.post" {{ request('type') === 'group.post' ? 'selected' : '' }}>پست‌های گروه</option>
                        <option value="group.poll" {{ request('type') === 'group.poll' ? 'selected' : '' }}>نظرسنجی‌ها</option>
                        <option value="group.comment" {{ request('type') === 'group.comment' ? 'selected' : '' }}>کامنت‌ها</option>
                        <option value="group.election" {{ request('type') === 'group.election' ? 'selected' : '' }}>انتخابات</option>
                        <option value="group.manager" {{ request('type') === 'group.manager' ? 'selected' : '' }}>اعلان‌های مدیر/بازرس</option>
                        <option value="chat" {{ request('type') === 'chat' ? 'selected' : '' }}>چت</option>
                        <option value="auction" {{ request('type') === 'auction' ? 'selected' : '' }}>حراج</option>
                        <option value="wallet" {{ request('type') === 'wallet' ? 'selected' : '' }}>کیف پول</option>
                        <option value="shares" {{ request('type') === 'shares' ? 'selected' : '' }}>سهام</option>
                        <option value="stock" {{ request('type') === 'stock' ? 'selected' : '' }}>اطلاعات سهام</option>
                        <option value="najm-bahar" {{ request('type') === 'najm-bahar' ? 'selected' : '' }}>نجم بهار</option>
                    </select>
                </div>

                @if(request('status') || request('type'))
                    <a href="{{ route('notifications.index') }}" class="btn-filter-clear">
                        <i class="fas fa-times"></i>
                        پاک کردن فیلترها
                    </a>
                @endif
            </form>
        </div>

        <!-- Notifications List -->
        @if($notifications->count() === 0)
            <div class="empty-state fade-in-section">
                <div class="empty-state-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h2 class="empty-state-title">اعلانی وجود ندارد</h2>
                <p class="empty-state-message">هنوز هیچ اعلانی دریافت نکرده‌اید. وقتی اعلان جدیدی دریافت کنید، اینجا نمایش داده می‌شود.</p>
            </div>
        @else
            <div class="notifications-list">
                @foreach($notifications as $notification)
                    <div class="notification-card fade-in-section {{ is_null($notification->read_at) ? 'unread' : '' }}">
                        <div class="notification-wrapper">
                            <!-- Icon -->
                            <div class="notification-icon {{ $notification->data['type'] ?? 'info' }}">
                                @php
                                    $type = $notification->data['type'] ?? 'info';
                                    $icons = [
                                        'info' => 'fa-info-circle',
                                        'success' => 'fa-check-circle',
                                        'warning' => 'fa-exclamation-triangle',
                                        'danger' => 'fa-times-circle',
                                        'najm-bahar.transaction' => 'fa-exchange-alt',
                                        'najm-bahar.low-balance' => 'fa-wallet',
                                        'najm-bahar.large-transaction' => 'fa-shield-alt',
                                        'najm-bahar.scheduled-executed' => 'fa-calendar-check',
                                    ];
                                    $icon = $icons[$type] ?? ($type === 'info' || $type === 'success' || $type === 'warning' || $type === 'danger' ? $icons[$type] : 'fa-bell');
                                @endphp
                                <i class="fas {{ $icon }}"></i>
                            </div>

                            <!-- Content -->
                            @if($notificationDestinations[$notification->id] ?? null)
                                <a href="{{ route('notifications.open', $notification->id) }}" class="notification-content notification-content-link">
                            @else
                                <div class="notification-content">
                            @endif
                                @php
                                    $notifType = $notification->data['type'] ?? 'other';
                                    $categoryMap = [
                                        'group.post' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'group.poll' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'group.comment.new' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'group.comment.reply' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'group.invitation' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'group.election.started' => ['name' => 'انتخابات', 'class' => 'category-elections'],
                                        'group.election.finished' => ['name' => 'انتخابات', 'class' => 'category-elections'],
                                        'group.election.elected' => ['name' => 'انتخابات', 'class' => 'category-elections'],
                                        'group.election.accepted' => ['name' => 'انتخابات', 'class' => 'category-elections'],
                                        'group.election.reminder' => ['name' => 'انتخابات', 'class' => 'category-elections'],
                                        'chat.message' => ['name' => 'چت', 'class' => 'category-chat'],
                                        'chat.reply' => ['name' => 'چت', 'class' => 'category-chat'],
                                        'chat.mention' => ['name' => 'چت', 'class' => 'category-chat'],
                                        'auction.started' => ['name' => 'حراج', 'class' => 'category-auction'],
                                        'auction.ended' => ['name' => 'حراج', 'class' => 'category-auction'],
                                        'auction.bid' => ['name' => 'حراج', 'class' => 'category-auction'],
                                        'auction.won' => ['name' => 'حراج', 'class' => 'category-auction'],
                                        'auction.outbid' => ['name' => 'حراج', 'class' => 'category-auction'],
                                        'najm-bahar.transaction' => ['name' => 'نجم بهار', 'class' => 'category-najm'],
                                        'najm-bahar.low-balance' => ['name' => 'نجم بهار', 'class' => 'category-najm'],
                                        'najm-bahar.large-transaction' => ['name' => 'نجم بهار', 'class' => 'category-najm'],
                                        'najm-bahar.scheduled-executed' => ['name' => 'نجم بهار', 'class' => 'category-najm'],
                                        'group.report.message' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'group.chat.request' => ['name' => 'گروه‌ها', 'class' => 'category-groups'],
                                        'admin.message' => ['name' => 'سیستم', 'class' => 'category-system'],
                                    ];
                                    $category = $categoryMap[$notifType] ?? ['name' => 'سایر', 'class' => 'category-other'];
                                @endphp
                                <span class="notification-category {{ $category['class'] }}">{{ $category['name'] }}</span>
                                <h3 class="notification-title">
                                    {{ $notification->data['title'] ?? 'اعلان' }}
                                </h3>
                                <p class="notification-message">
                                    {{ $notification->data['message'] ?? json_encode($notification->data, JSON_UNESCAPED_UNICODE) }}
                                </p>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <span>{{ $notification->created_at->diffForHumans() }}</span>
                                    <span>•</span>
                                    <span>{{ $notification->created_at->format('Y/m/d H:i') }}</span>
                                </div>
                            @if($notificationDestinations[$notification->id] ?? null)
                                </a>
                            @else
                                </div>
                            @endif

                            <!-- Actions - Always horizontal -->
                            <div class="notification-actions">
                                @if(is_null($notification->read_at))
                                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn-notification read">
                                            <i class="fas fa-check"></i>
                                            <span>خواندم</span>
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('notifications.delete', $notification->id) }}" method="POST" class="m-0" onsubmit="return confirm('آیا از حذف این اعلان اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-notification delete">
                                        <i class="fas fa-trash"></i>
                                        <span>حذف</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($notifications->hasPages())
                <div class="pagination-wrapper">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </main>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.fade-in-section');

        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('is-visible');
                    }, index * 50);
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        sections.forEach(section => {
            observer.observe(section);
        });
    });
</script>
@endpush
