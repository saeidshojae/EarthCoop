@extends('layouts.admin')

@section('title', 'لاگ کدهای دعوت')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-history ml-2"></i>
                لاگ کدهای دعوت
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مشاهده و فیلتر لاگ اقدامات مربوط به کدهای دعوت</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.invitation_codes.logs.export', request()->all()) }}"
               class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                <i class="fas fa-file-csv ml-2"></i>
                خروجی CSV
            </a>
            <a href="{{ route('admin.invitation_codes.index') }}"
               class="inline-flex items-center px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition-colors">
                <i class="fas fa-arrow-right ml-2"></i>
                بازگشت
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
        <form method="GET" action="{{ route('admin.invitation_codes.logs') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">اقدام</label>
                <select name="action" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    @foreach($actions as $act)
                        <option value="{{ $act }}" {{ request('action')===$act ? 'selected' : '' }}>{{ $act }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">از تاریخ</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">تا تاریخ</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">جستجو در کد</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="مثال: EARTH" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Actor ID</label>
                <input type="number" name="actor_id" value="{{ request('actor_id') }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div class="md:col-span-6 flex items-center justify-end gap-2">
                <a href="{{ route('admin.invitation_codes.logs') }}" class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">پاک‌سازی</a>
                <button class="px-4 py-2 rounded-lg bg-blue-600 text-white">اعمال فیلتر</button>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">کد</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">اقدام</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">صادرکننده</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عامل</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">متادیتا</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ $log->id }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ optional($log->code)->code }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ optional(optional($log->code)->user)->fullName() ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ optional($log->actor)->fullName() ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                <pre class="whitespace-pre-wrap break-words">{{ json_encode($log->meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">لاگی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection


