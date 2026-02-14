@extends('layouts.unified')

@section('title', 'اعلان‌ها - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    /* Custom Tailwind Configuration - Color and font configuration */
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
    }

    /* Font Families */
    .font-vazirmatn { font-family: 'Vazirmatn', sans-serif; }
    .font-poppins { font-family: 'Poppins', sans-serif; }

    /* Notification Card Styles */
    .notification-card {
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 16px;
        padding: 1.5rem;
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

    .notification-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        margin-left: 1rem;
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
        color: #ef4444;
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notification-message {
        font-size: 0.95rem;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 0.75rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notification-time {
        font-size: 0.85rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notification-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .btn-notification {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-family: 'Vazirmatn', sans-serif;
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

    /* Empty State */
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

    /* Header Actions */
    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .notifications-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-gentle-black);
        display: flex;
        align-items: center;
        gap: 1rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .notifications-title-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-pure-white);
        font-size: 1.5rem;
    }

    .header-actions {
        display: flex;
        gap: 0.75rem;
    }

    .btn-header-action {
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
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

    /* Pagination */
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
        gap: 0.5rem;
        background: var(--color-light-gray);
        padding: 0.75rem;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .pagination-wrapper .pagination li {
        margin: 0;
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

    .pagination-wrapper .pagination li.disabled span {
        color: #cbd5e1;
        cursor: not-allowed;
    }

    /* Fade-in animation */
    .fade-in-section {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }

    .fade-in-section.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .notifications-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .header-actions {
            width: 100%;
            flex-direction: column;
        }

        .btn-header-action {
            width: 100%;
            justify-content: center;
        }

        .notification-card {
            padding: 1rem;
        }

        .notification-actions {
            flex-direction: column;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-notification {
            width: 100%;
        }
    }

    /* Badge for unread count */
    .unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--color-ocean-blue);
        color: var(--color-pure-white);
        font-size: 0.75rem;
        font-weight: 700;
        margin-right: 0.5rem;
    }
</style>
@endpush

@section('content')
<div class="container mx-auto flex flex-col lg:flex-row gap-8 p-6 md:p-8">
    @include('partials.sidebar-unified')
    
    <main class="flex-grow fade-in-section">
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
                        <div class="flex items-start gap-4">
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
                            <div class="notification-content">
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
                            </div>

                            <!-- Actions -->
                            <div class="notification-actions">
                                @if(is_null($notification->read_at))
                                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn-notification read">
                                            <i class="fas fa-check ml-1"></i>
                                            <span>خواندم</span>
                                        </button>
                                    </form>
                                @endif
                                
                                <form action="{{ route('notifications.delete', $notification->id) }}" method="POST" class="m-0" onsubmit="return confirm('آیا از حذف این اعلان اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-notification delete">
                                        <i class="fas fa-trash ml-1"></i>
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

@push('scripts')
<script>
    // Fade-in animation
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
                    }, index * 50); // Stagger animation
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
@endsection

