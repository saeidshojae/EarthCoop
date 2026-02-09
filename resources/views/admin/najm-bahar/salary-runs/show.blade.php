@extends('layouts.admin')

@section('title', 'جزئیات اجرای حقوق')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-clipboard-list ml-2"></i>
                جزئیات اجرای حقوق
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">بازه {{ $salaryRun->period_start }} تا {{ $salaryRun->period_end }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('admin.najm-bahar.salary-runs.process', $salaryRun) }}" method="POST" onsubmit="return confirm('پرداخت‌ها اجرا شود؟');">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-play ml-2"></i>
                    اجرای پرداخت‌ها
                </button>
            </form>
            <a href="{{ route('admin.najm-bahar.salary-runs.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                بازگشت
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                <select name="status" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    <option value="ready" {{ request('status') === 'ready' ? 'selected' : '' }}>آماده پرداخت</option>
                    <option value="blocked" {{ request('status') === 'blocked' ? 'selected' : '' }}>مسدود</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>پرداخت شده</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>ناموفق</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-search ml-2"></i>
                فیلتر
            </button>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">کاربر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">گروه</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">بازه</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">مبلغ</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">امتیاز</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">تایید ارشد</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($items as $item)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ $item->user_id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">{{ $item->group_id ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">{{ $item->period_start }} تا {{ $item->period_end }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ \\App\\Helpers\\BaharMoney::formatDecimal($item->amount_gol) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form action="{{ route('admin.najm-bahar.salary-runs.items.update', [$salaryRun, $item]) }}" method="POST" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" name="activity_score" value="{{ $item->activity_score }}" min="0"
                                           class="w-20 px-2 py-1 border border-slate-300 rounded-lg text-sm">
                                    <button type="submit" class="text-xs text-blue-600">ثبت</button>
                                </form>
                                <div class="text-xs text-slate-400">حداقل: {{ $item->activity_threshold ?? 0 }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form action="{{ route('admin.najm-bahar.salary-runs.items.update', [$salaryRun, $item]) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="senior_approved" value="0">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="senior_approved" value="1" {{ $item->senior_approved_at ? 'checked' : '' }}
                                               class="w-4 h-4 text-emerald-600 border-slate-300 rounded">
                                        تایید
                                    </label>
                                    <button type="submit" class="text-xs text-blue-600 mr-2">ثبت</button>
                                </form>
                                @if($item->senior_approved_at)
                                    <div class="text-xs text-slate-400">{{ $item->senior_approved_at }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $item->status === 'paid' ? 'bg-green-100 text-green-800' : ($item->status === 'ready' ? 'bg-blue-100 text-blue-800' : ($item->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ $item->status }}
                                </span>
                                @if($item->blocked_reason)
                                    <div class="text-xs text-slate-400 mt-1">{{ $item->blocked_reason }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                                @if($item->transaction_id)
                                    تراکنش #{{ $item->transaction_id }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <i class="fas fa-inbox text-4xl mb-3 opacity-50"></i>
                                <p>هیچ آیتمی ثبت نشده است</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
