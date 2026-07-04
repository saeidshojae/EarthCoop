@extends('layouts.admin')

@section('title', 'گزارش‌های چت خصوصی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-shield-alt ml-2"></i>
                گزارش‌های چت خصوصی
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">بررسی، تعیین تکلیف و مدیریت گزارش‌های پیام‌های خصوصی</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-flag ml-2"></i>
            گزارشات عمومی
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <p class="text-sm text-slate-500 dark:text-slate-400">کل گزارش‌ها</p>
            <p class="text-3xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <p class="text-sm text-slate-500 dark:text-slate-400">در انتظار</p>
            <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($stats['pending'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <p class="text-sm text-slate-500 dark:text-slate-400">بررسی شده</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ number_format($stats['reviewed'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <p class="text-sm text-slate-500 dark:text-slate-400">حل شده</p>
            <p class="text-3xl font-bold text-emerald-600 mt-1">{{ number_format($stats['resolved'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <p class="text-sm text-slate-500 dark:text-slate-400">رد شده</p>
            <p class="text-3xl font-bold text-rose-600 mt-1">{{ number_format($stats['dismissed'] ?? 0) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm mb-6">
        <form method="GET" action="{{ route('admin.private-chat-reports') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                <select name="status" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    @foreach(\App\Models\PrivateChatReport::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">دلیل</label>
                <select name="reason" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    @foreach(\App\Models\PrivateChatReport::REASONS as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['reason'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="نام، ایمیل، متن پیام">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-search ml-2"></i>
                    اعمال فیلتر
                </button>
                <a href="{{ route('admin.private-chat-reports') }}" class="inline-flex items-center px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors dark:bg-slate-700 dark:text-slate-200">
                    پاک کردن
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/40">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">گزارش</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">افراد</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">وضعیت</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">زمان</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($reports as $report)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                            <td class="px-4 py-4 align-top">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ \App\Models\PrivateChatReport::REASONS[$report->reason] ?? $report->reason }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ \Illuminate\Support\Str::limit($report->description ?: optional($report->message)->message, 110) }}</div>
                            </td>
                            <td class="px-4 py-4 align-top text-sm text-slate-600 dark:text-slate-300">
                                <div>گزارش‌دهنده: {{ $report->reporter?->fullName() ?? $report->reporter?->email ?? '-' }}</div>
                                <div>مورد گزارش: {{ $report->reportedUser?->fullName() ?? $report->reportedUser?->email ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-4 align-top">
                                @php
                                    $statusClasses = [
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'reviewed' => 'bg-blue-100 text-blue-800',
                                        'resolved' => 'bg-emerald-100 text-emerald-800',
                                        'dismissed' => 'bg-rose-100 text-rose-800',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $statusClasses[$report->status] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ \App\Models\PrivateChatReport::STATUSES[$report->status] ?? $report->status }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top text-sm text-slate-500 dark:text-slate-400">
                                {{ optional($report->created_at)->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-4 py-4 align-top">
                                <a href="{{ route('admin.private-chat-reports.show', $report->id) }}" class="inline-flex items-center px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition-colors text-sm">
                                    <i class="fas fa-eye ml-2"></i>
                                    بررسی
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">گزارشی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $reports->links() }}
        </div>
    </div>
</div>
@endsection