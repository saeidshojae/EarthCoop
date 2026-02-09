@extends('layouts.unified')

@section('title', 'مشارکت‌های من - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Contributions Section Styles from mosharekat.html */
    .contributions-section {
        width: 100%;
        background-color: var(--color-pure-white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 2.5rem;
        margin-top: 0;
        border: 1px solid #e2e8f0;
        animation: fadeIn 0.8s ease-out;
    }

    .contributions-title {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--color-dark-green);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid var(--color-earth-green);
        position: relative;
        text-align: right;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .contributions-title::after {
        content: '';
        position: absolute;
        bottom: -3px;
        right: 0;
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, var(--color-digital-gold), var(--color-accent-peach));
        border-radius: 2px;
    }

    .tabs-navigation {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 30px;
        background-color: var(--color-light-gray);
        border-radius: 0.75rem;
        padding: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        flex-wrap: wrap;
        gap: 5px;
    }

    .tab-button {
        background-color: transparent;
        border: none;
        padding: 12px 20px;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        cursor: pointer;
        border-radius: 0.75rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        flex-grow: 1;
        text-align: center;
        min-width: 150px;
    }

    .tab-button:hover {
        color: var(--color-earth-green);
        background-color: #eef2f6;
        transform: translateY(-2px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .tab-button.active {
        background: linear-gradient(45deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: var(--color-pure-white);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
    }

    .tab-content {
        background-color: var(--color-pure-white);
        padding: 25px;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        animation: fadeIn 0.6s ease-out;
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .tab-content h3 {
        font-size: 1.4rem;
        color: var(--color-dark-green);
        margin-bottom: 20px;
        text-align: right;
    }

    /* Table wrapper for horizontal scrolling */
    .data-table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .data-table {
        width: 100%;
        min-width: 700px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .data-table th, .data-table td {
        padding: 15px 20px;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
        border-left: 1px solid #e2e8f0;
    }

    .data-table th {
        background: linear-gradient(180deg, var(--color-light-gray) 0%, #f1f5f9 100%);
        color: var(--color-dark-green);
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table th:first-child {
        border-top-right-radius: 0.75rem;
    }

    .data-table th:last-child {
        border-top-left-radius: 0.75rem;
        border-left: none;
    }

    .data-table tbody tr:nth-child(even) {
        background-color: var(--color-light-gray);
    }

    .data-table tbody tr:hover {
        background-color: var(--color-earth-green);
        color: var(--color-pure-white);
        transform: scale(1.01);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .data-table td {
        color: var(--color-gentle-black);
        font-size: 0.9rem;
        transition: all 0.15s ease-out;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table tbody tr:last-child td:first-child {
        border-bottom-right-radius: 0.75rem;
    }
    .data-table tbody tr:last-child td:last-child {
        border-bottom-left-radius: 0.75rem;
        border-left: none;
    }

    .data-table a {
        color: var(--color-earth-green);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }

    .data-table tbody tr:hover a {
        color: var(--color-pure-white);
    }

    /* Pagination */
    .pagination-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 25px;
        gap: 8px;
        padding: 10px;
        background-color: var(--color-light-gray);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .pagination-button {
        background-color: transparent;
        border: none;
        padding: 10px 15px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        cursor: pointer;
        border-radius: 0.75rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        min-width: 40px;
        text-align: center;
    }

    .pagination-button:hover {
        color: var(--color-earth-green);
        background-color: #eef2f6;
        transform: translateY(-2px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .pagination-button.active {
        background: linear-gradient(45deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: var(--color-pure-white);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
    }

    /* Dark Mode Support */
    body.dark-mode .contributions-section {
        background-color: #2d2d2d;
        border-color: #404040;
    }

    body.dark-mode .contributions-title {
        color: #e0e0e0;
    }

    body.dark-mode .tab-content {
        background-color: #2d2d2d;
        border-color: #404040;
    }

    body.dark-mode .data-table th {
        background: linear-gradient(180deg, #3a3a3a 0%, #2d2d2d 100%);
        color: #e0e0e0;
    }

    body.dark-mode .data-table tbody tr:nth-child(even) {
        background-color: #3a3a3a;
    }

    body.dark-mode .data-table tbody tr:hover {
        background-color: var(--color-earth-green);
        color: var(--color-pure-white);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .contributions-section {
            padding: 1.5rem;
        }
        .contributions-title {
            font-size: 1.6rem;
        }
        .tabs-navigation {
            padding: 6px;
        }
        .tab-button {
            padding: 8px 10px;
            font-size: 0.85rem;
            min-width: 120px;
        }
        .tab-content {
            padding: 15px;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            font-size: 0.8rem;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Contributions Section -->
    <section class="contributions-section">
        <h2 class="contributions-title">مشارکت‌های شما</h2>

        <div class="tabs-navigation">
            <button class="tab-button active" data-tab="posts">پست‌های شما</button>
            <button class="tab-button" data-tab="comments">نظرات شما</button>
            <button class="tab-button" data-tab="replies">پاسخ‌ها به شما</button>
            <button class="tab-button" data-tab="reactions">واکنش‌های شما</button>
            <button class="tab-button" data-tab="polls">نظرسنجی‌های شما</button>
            <button class="tab-button" data-tab="votes">رأی‌های شما</button>
            <button class="tab-button" data-tab="points">امتیازات من</button>
        </div>

        <!-- Tab 1: Posts -->
        <div id="tab-posts" class="tab-content active">
            <h3>پست‌های شما</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>نام پست</th>
                            <th>تعداد لایک</th>
                            <th>تعداد دیسلایک</th>
                            <th>تعداد نظر</th>
                            <th>گروه</th>
                            <th>تاریخ ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($blogs as $blog)
                        <tr>
                            <td>
                                <a href="{{ route('groups.chat', [$blog->group->id, '#blog-' . $blog->id]) }}">
                                    {{ $blog->title }}
                                </a>
                            </td>
                            <td>{{ $blog->likes()->count() }}</td>
                            <td>{{ $blog->dislikes()->count() }}</td>
                            <td>{{ $blog->comments()->count() }}</td>
                            <td>{{ $blog->group->name }}</td>
                            <td>{{ verta($blog->created_at)->format('Y-m-d') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                هیچ پستی یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Points -->
        <div id="tab-points" class="tab-content">
            <h3>امتیازات شما</h3>
            <div class="mb-4">
                <div class="text-lg font-bold">امتیاز فعلی: {{ number_format($currentPoints) }} امتیاز</div>
                <p class="text-sm text-slate-500">در این جدول می‌توانید تاریخچه تراکنش‌های امتیاز خود را مشاهده کنید.</p>
            </div>

            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>عمل</th>
                            <th>تغییر</th>
                            <th>تراز پس از</th>
                            <th>ماخذ</th>
                            <th>توضیحات</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pointTransactions as $tx)
                        <tr>
                            <td>{{ $tx->action }}</td>
                            <td>{{ $tx->delta > 0 ? '+' . $tx->delta : $tx->delta }}</td>
                            <td>{{ $tx->balance_after }}</td>
                            <td>{{ $tx->source ?? '-' }}</td>
                            <td>{{ optional($tx->metadata)['reason'] ?? (is_string($tx->metadata) ? $tx->metadata : '-') }}</td>
                            <td>{{ verta($tx->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">هیچ تراکنشی یافت نشد</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 2: Comments -->
        <div id="tab-comments" class="tab-content">
            <h3>نظرات شما</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>محتوا</th>
                            <th>تعداد لایک</th>
                            <th>تعداد دیسلایک</th>
                            <th>تعداد ریپلای</th>
                            <th>پست</th>
                            <th>گروه</th>
                            <th>تاریخ ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comments as $comment)
                        <tr>
                            <td>
                                <a href="{{ route('groups.comment', [$comment->blog->id, '#msg-' . $comment->id]) }}">
                                    {{ Str::limit($comment->message, 50) }}
                                </a>
                            </td>
                            <td>{{ $comment->likes()->count() }}</td>
                            <td>{{ $comment->dislikes()->count() }}</td>
                            <td>{{ $comment->childs()->count() }}</td>
                            <td>{{ Str::limit($comment->blog->title, 30) }}</td>
                            <td>{{ $comment->blog->group->name }}</td>
                            <td>{{ verta($comment->created_at)->format('Y-m-d') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                هیچ نظری یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 3: Replies -->
        <div id="tab-replies" class="tab-content">
            <h3>پاسخ‌ها به شما</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>محتوا</th>
                            <th>تعداد لایک</th>
                            <th>تعداد دیسلایک</th>
                            <th>تعداد ریپلای</th>
                            <th>نظر</th>
                            <th>پست</th>
                            <th>گروه</th>
                            <th>تاریخ ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $replies = collect();
                            foreach($comments as $comment) {
                                foreach($comment->childs()->with('blog.group')->get() as $child) {
                                    if($child->user_id != auth()->id()) {
                                        $replies->push($child);
                                    }
                                }
                            }
                        @endphp
                        @forelse($replies as $reply)
                        <tr>
                            <td>
                                <a href="{{ route('groups.comment', [$reply->blog->id, '#msg-' . ($reply->parent_id ?? $reply->id)]) }}">
                                    {{ Str::limit($reply->message, 50) }}
                                </a>
                            </td>
                            <td>{{ $reply->likes()->count() }}</td>
                            <td>{{ $reply->dislikes()->count() }}</td>
                            <td>{{ $reply->childs()->count() }}</td>
                            <td>{{ Str::limit($reply->parent->message ?? '—', 30) }}</td>
                            <td>{{ Str::limit($reply->blog->title ?? '—', 30) }}</td>
                            <td>{{ $reply->blog->group->name ?? '—' }}</td>
                            <td>{{ verta($reply->created_at)->format('Y-m-d') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                هیچ پاسخی یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 4: Reactions -->
        <div id="tab-reactions" class="tab-content">
            <h3>واکنش‌های شما</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>پست/نظر</th>
                            <th>برای</th>
                            <th>گروه</th>
                            <th>تاریخ ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reactions as $reaction)
                        <tr>
                            <td>{{ $reaction->type == 1 ? 'لایک' : 'دیسلایک' }}</td>
                            <td>
                                @if($reaction->react_type == 1 && $reaction->comment?->blog?->id)
                                    <a href="{{ route('groups.comment', [$reaction->comment->blog->id, '#msg-' . $reaction->comment->id]) }}">
                                        {{ Str::limit($reaction->comment?->message ?? '—', 50) }}
                                    </a>
                                @elseif($reaction->react_type == 2 && $reaction->blog?->group?->id)
                                    <a href="{{ route('groups.chat', [$reaction->blog->group->id, '#blog-' . $reaction->blog->id]) }}">
                                        {{ Str::limit($reaction->blog?->title ?? '—', 50) }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $reaction->react_type == 1 ? 'کامنت' : 'پست' }}</td>
                            <td>
                                @if($reaction->react_type == 1)
                                    {{ $reaction->comment?->blog?->group?->name ?? '—' }}
                                @else
                                    {{ $reaction->blog?->group?->name ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $reaction->created_at ? verta($reaction->created_at)->format('Y-m-d') : '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-gray-500">
                                هیچ واکنشی یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 5: Polls -->
        <div id="tab-polls" class="tab-content">
            <h3>نظرسنجی‌های شما</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>رای شما</th>
                            <th>نظرسنجی</th>
                            <th>گروه</th>
                            <th>تاریخ ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($polls as $poll)
                        <tr>
                            <td>
                                <a href="{{ route('groups.chat', [$poll->poll->group->id, '#poll-' . $poll->poll->id]) }}">
                                    {{ $poll->option->text }}
                                </a>
                            </td>
                            <td>{{ $poll->poll->question }}</td>
                            <td>{{ $poll->poll->group->name }}</td>
                            <td>{{ verta($poll->created_at)->format('Y-m-d') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 text-gray-500">
                                هیچ نظرسنجی‌ای یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 6: Votes -->
        <div id="tab-votes" class="tab-content">
            <h3>رأی‌های شما</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>رای به</th>
                            <th>به عنوان</th>
                            <th>گروه</th>
                            <th>تاریخ ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($elections as $election)
                        <tr>
                            <td>{{ $election->user->fullName() }}</td>
                            <td>{{ $election->position == 0 ? 'بازرس' : 'مدیر' }}</td>
                            <td>{{ $election->election->group->name }}</td>
                            <td>{{ verta($election->created_at)->format('Y-m-d') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 text-gray-500">
                                هیچ رای‌ای یافت نشد
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Tab functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove 'active' class from all buttons and hide all contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Add 'active' class to the clicked button
                button.classList.add('active');

                // Display the corresponding tab content
                const targetTab = button.dataset.tab;
                document.getElementById(`tab-${targetTab}`).classList.add('active');
            });
        });
    });
</script>
@endpush
