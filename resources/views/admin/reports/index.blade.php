@extends('layouts.admin')

@section('title', 'مدیریت گزارشات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-flag ml-2"></i>
                مدیریت گزارشات
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مدیریت و بررسی تمام گزارشات کاربران</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل گزارشات</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['total'] ?? 0) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-flag text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm mb-1">در انتظار بررسی</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['pending'] ?? 0) }}</p>
                </div>
                <div class="bg-orange-400/20 rounded-full p-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">حل شده</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['resolved'] ?? 0) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm mb-1">رد شده</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['rejected'] ?? 0) }}</p>
                </div>
                <div class="bg-red-400/20 rounded-full p-4">
                    <i class="fas fa-times-circle text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">اولویت بالا</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['high_priority'] ?? 0) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودار روند گزارشات -->
    @if(isset($chartData) && count($chartData['labels']) > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-chart-line ml-2"></i>
            روند گزارشات (12 ماه گذشته)
        </h3>
        <canvas id="reportsChart" style="max-height: 300px;"></canvas>
    </div>
    @endif

    <!-- فیلترها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-filter ml-2"></i>
            فیلترها
        </h3>
        
        <form method="GET" action="{{ route('admin.reports.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">نوع گزارش</label>
                    <select name="type" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="">همه انواع</option>
                        <option value="message" {{ $filters['type'] == 'message' ? 'selected' : '' }}>پیام</option>
                        <option value="post" {{ $filters['type'] == 'post' ? 'selected' : '' }}>پست</option>
                        <option value="poll" {{ $filters['type'] == 'poll' ? 'selected' : '' }}>نظرسنجی</option>
                        <option value="user" {{ $filters['type'] == 'user' ? 'selected' : '' }}>کاربر</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                    <select name="status" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="pending" {{ $filters['status'] == 'pending' ? 'selected' : '' }}>در انتظار بررسی</option>
                        <option value="reviewed" {{ $filters['status'] == 'reviewed' ? 'selected' : '' }}>بررسی شده</option>
                        <option value="resolved" {{ $filters['status'] == 'resolved' ? 'selected' : '' }}>حل شده</option>
                        <option value="rejected" {{ $filters['status'] == 'rejected' ? 'selected' : '' }}>رد شده</option>
                        <option value="archived" {{ $filters['status'] == 'archived' ? 'selected' : '' }}>بایگانی شده</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">اولویت</label>
                    <select name="priority" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="">همه اولویت‌ها</option>
                        <option value="low" {{ $filters['priority'] == 'low' ? 'selected' : '' }}>پایین</option>
                        <option value="medium" {{ $filters['priority'] == 'medium' ? 'selected' : '' }}>متوسط</option>
                        <option value="high" {{ $filters['priority'] == 'high' ? 'selected' : '' }}>بالا</option>
                        <option value="critical" {{ $filters['priority'] == 'critical' ? 'selected' : '' }}>بحرانی</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                    <input type="text" 
                           name="search" 
                           value="{{ $filters['search'] }}"
                           placeholder="جستجو در دلایل و توضیحات..."
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">از تاریخ</label>
                    <input type="text" 
                           name="date_from" 
                           value="{{ $filters['date_from'] }}"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg jalali-date focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="1404/01/01">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تا تاریخ</label>
                    <input type="text" 
                           name="date_to" 
                           value="{{ $filters['date_to'] }}"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg jalali-date focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="1404/12/29">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">گزارش‌دهنده (ID)</label>
                    <input type="number" 
                           name="reporter" 
                           value="{{ $filters['reporter'] }}"
                           placeholder="شناسه کاربر"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search ml-2"></i>
                    اعمال فیلترها
                </button>
                <a href="{{ route('admin.reports.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    پاک کردن
                </a>
            </div>
        </form>
    </div>

    <!-- عملیات دسته‌جمعی -->
    @hasPermission('reports.manage')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
        <form id="bulkActionForm" class="flex items-center gap-3">
            @csrf
            <select id="bulkAction" 
                    class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                <option value="">-- عملیات دسته‌جمعی --</option>
                <option value="approve">تأیید (حل شده)</option>
                <option value="reject">رد کردن</option>
                <option value="archive">بایگانی</option>
                <option value="delete">حذف</option>
            </select>
            <button type="submit" 
                    class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-check ml-2"></i>
                اعمال به انتخاب شده‌ها
            </button>
            <span id="selectedCount" class="text-sm text-slate-600 dark:text-slate-400">0 مورد انتخاب شده</span>
        </form>
    </div>
    @endhasPermission

    <!-- جدول گزارشات -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                <i class="fas fa-list ml-2"></i>
                لیست گزارشات
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="reportsTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        @hasPermission('reports.manage')
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        @endhasPermission
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نوع</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">مورد گزارش</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">گزارش‌دهنده</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">دلیل</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">اولویت</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($reports as $index => $report)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors" data-report-id="{{ $report->id }}">
                            @hasPermission('reports.manage')
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       name="report_ids[]" 
                                       value="{{ $report->id }}"
                                       class="report-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            @endhasPermission
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ ($reports->currentPage() - 1) * $reports->perPage() + $index + 1 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $typeLabels = [
                                        'message' => 'پیام',
                                        'post' => 'پست',
                                        'poll' => 'نظرسنجی',
                                        'user' => 'کاربر'
                                    ];
                                    $typeColors = [
                                        'message' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'post' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'poll' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                        'user' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ];
                                    $type = $report->type ?? 'message';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$type] ?? 'bg-gray-100 text-gray-800' }}">
                                    <i class="fas {{ $type == 'message' ? 'fa-comment' : ($type == 'post' ? 'fa-file-alt' : ($type == 'poll' ? 'fa-poll' : 'fa-user')) }} ml-1"></i>
                                    {{ $typeLabels[$type] ?? $type }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-900 dark:text-white">
                                    @if(isset($report->source) && $report->source === 'old')
                                        <!-- گزارش قدیمی از پیام -->
                                        @if($report->message)
                                            <div class="max-w-xs">
                                                <div class="font-medium">{{ $report->message->user->fullName() ?? 'کاربر حذف شده' }}</div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                                    {{ Str::limit($report->message->message ?? '-', 50) }}
                                                </div>
                                            </div>
                                        @elseif($report->group_id)
                                            <span>{{ \App\Models\Group::find($report->group_id)->name ?? '-' }}</span>
                                        @endif
                                    @else
                                        <!-- گزارش جدید -->
                                        @php
                                            $itemId = $report->reported_item_id ?? null;
                                        @endphp
                                        @if($itemId)
                                            <a href="{{ route('admin.reports.show', $report->id) }}" 
                                               class="text-blue-600 dark:text-blue-400 hover:underline">
                                                مشاهده (ID: {{ $itemId }})
                                            </a>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $reporter = $report->reporter ?? null;
                                @endphp
                                @if($reporter)
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-xs ml-2">
                                            {{ mb_substr($reporter->first_name ?? 'U', 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $reporter->fullName() }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                #{{ $reporter->id }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-sm text-slate-400">کاربر حذف شده</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600 dark:text-slate-400 max-w-xs truncate">
                                    {{ $report->reason ?? '-' }}
                                </div>
                                @if($report->description)
                                    <div class="text-xs text-slate-500 dark:text-slate-500 mt-1 truncate">
                                        {{ Str::limit($report->description, 30) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusLabels = [
                                        'pending' => 'در انتظار بررسی',
                                        'reviewed' => 'بررسی شده',
                                        'resolved' => 'حل شده',
                                        'rejected' => 'رد شده',
                                        'archived' => 'بایگانی شده'
                                    ];
                                    $statusColors = [
                                        'pending' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                        'reviewed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'resolved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                        'archived' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                    ];
                                    $status = $report->status ?? 'pending';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusLabels[$status] ?? $status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $priorityLabels = [
                                        'low' => 'پایین',
                                        'medium' => 'متوسط',
                                        'high' => 'بالا',
                                        'critical' => 'بحرانی'
                                    ];
                                    $priorityColors = [
                                        'low' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                        'critical' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ];
                                    $priority = $report->priority ?? 'medium';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $priorityColors[$priority] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $priorityLabels[$priority] ?? $priority }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                @php
                                    try {
                                        $date = isset($report->created_at) ? \Carbon\Carbon::parse($report->created_at) : null;
                                        echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                    } catch (\Exception $e) {
                                        echo '-';
                                    }
                                @endphp
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.reports.show', $report->id) }}" 
                                       class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-eye ml-1"></i>
                                        مشاهده
                                    </a>
                                    @hasPermission('reports.manage')
                                    <button onclick="updateReportStatus({{ $report->id }}, 'resolved')" 
                                            class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition-colors">
                                        <i class="fas fa-check ml-1"></i>
                                    </button>
                                    <button onclick="updateReportStatus({{ $report->id }}, 'rejected')" 
                                            class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors">
                                        <i class="fas fa-times ml-1"></i>
                                    </button>
                                    @endhasPermission
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasPermission('reports.manage') ? 10 : 9 }}" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-flag text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ گزارشی یافت نشد</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($reports->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $reports->links() }}
        </div>
        @endif
    </div>
</div>

@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    $(document).ready(function() {
        // DataTables initialization
        if ($('#reportsTable').length) {
            $('#reportsTable').DataTable({
                language: {
                    "decimal": "",
                    "emptyTable": "هیچ داده‌ای در جدول وجود ندارد",
                    "info": "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
                    "infoEmpty": "نمایش 0 تا 0 از 0 رکورد",
                    "infoFiltered": "(فیلتر شده از مجموع _MAX_ رکورد)",
                    "lengthMenu": "نمایش _MENU_ رکورد",
                    "loadingRecords": "در حال بارگذاری...",
                    "processing": "در حال پردازش...",
                    "search": "جستجو:",
                    "zeroRecords": "رکوردی یافت نشد",
                    "paginate": {
                        "first": "اولین",
                        "last": "آخرین",
                        "next": "بعدی",
                        "previous": "قبلی"
                    }
                },
                pageLength: 25,
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: [0, 9] }
                ],
                paging: false // استفاده از Laravel pagination
            });
        }
        
        // نمودار روند گزارشات
        @if(isset($chartData) && count($chartData['labels']) > 0)
        const ctx = document.getElementById('reportsChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($chartData['labels']),
                    datasets: [{
                        label: 'تعداد گزارشات',
                        data: @json($chartData['data']),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        @endif
        
        // انتخاب همه
        $('#selectAll').on('change', function() {
            $('.report-checkbox').prop('checked', $(this).prop('checked'));
            updateSelectedCount();
        });
        
        // به‌روزرسانی تعداد انتخاب شده
        $('.report-checkbox').on('change', function() {
            updateSelectedCount();
            // اگر همه انتخاب شده‌اند، selectAll را هم انتخاب کن
            const total = $('.report-checkbox').length;
            const checked = $('.report-checkbox:checked').length;
            $('#selectAll').prop('checked', total === checked && total > 0);
        });
        
        function updateSelectedCount() {
            const count = $('.report-checkbox:checked').length;
            $('#selectedCount').text(count + ' مورد انتخاب شده');
        }
        
        // عملیات دسته‌جمعی
        $('#bulkActionForm').on('submit', function(e) {
            e.preventDefault();
            
            const action = $('#bulkAction').val();
            const selectedIds = $('.report-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (!action) {
                alert('لطفاً یک عملیات انتخاب کنید');
                return;
            }
            
            if (selectedIds.length === 0) {
                alert('لطفاً حداقل یک گزارش را انتخاب کنید');
                return;
            }
            
            if (!confirm(`آیا مطمئن هستید که می‌خواهید عملیات "${action}" را روی ${selectedIds.length} گزارش انجام دهید؟`)) {
                return;
            }
            
            $.ajax({
                url: '{{ route('admin.reports.bulk-action') }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    action: action,
                    report_ids: selectedIds
                },
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('خطا در انجام عملیات');
                    }
                },
                error: function() {
                    alert('خطا در انجام عملیات');
                }
            });
        });
    });
    
    // به‌روزرسانی وضعیت گزارش
    function updateReportStatus(reportId, status) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید وضعیت این گزارش را تغییر دهید؟')) {
            return;
        }
        
        $.ajax({
            url: `/admin/reports/${reportId}`,
            method: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                status: status
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('خطا در به‌روزرسانی گزارش');
                }
            },
            error: function() {
                alert('خطا در به‌روزرسانی گزارش');
            }
        });
    }
</script>
@endpush
@endsection
