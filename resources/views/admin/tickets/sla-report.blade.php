@extends('layouts.admin')

@section('title', 'گزارش عملکرد SLA')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('admin.tickets.index') }}" 
               class="inline-flex items-center text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white mb-2">
                <i class="fas fa-arrow-right ml-2"></i>
                بازگشت به لیست تیکت‌ها
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-chart-line ml-2"></i>
                گزارش عملکرد SLA
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                آمار و تحلیل عملکرد SLA تیکت‌های پشتیبانی
            </p>
        </div>
        <a href="{{ route('admin.tickets.index') }}" 
           class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-ticket-alt ml-2"></i>
            لیست تیکت‌ها
        </a>
    </div>

    <!-- فیلتر تاریخ -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.tickets.sla-report') }}" class="flex items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">از تاریخ</label>
                <input type="date" 
                       name="from" 
                       value="{{ request('from', $fromDate->format('Y-m-d')) }}"
                       class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تا تاریخ</label>
                <input type="date" 
                       name="to" 
                       value="{{ request('to', $toDate->format('Y-m-d')) }}"
                       class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <button type="submit" 
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-filter ml-2"></i>
                    اعمال فیلتر
                </button>
            </div>
        </form>
    </div>

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- کل تیکت‌های بسته شده -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">کل تیکت‌های بسته شده</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total_tickets']) }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <i class="fas fa-ticket-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- درصد رعایت SLA اولین پاسخ -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">رعایت SLA اولین پاسخ</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['first_response_sla_percentage'] }}%</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ number_format($stats['met_first_response_sla']) }} از {{ number_format($stats['total_tickets']) }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <i class="fas fa-clock text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- درصد رعایت SLA حل مشکل -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">رعایت SLA حل مشکل</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['resolution_sla_percentage'] }}%</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ number_format($stats['met_resolution_sla']) }} از {{ number_format($stats['total_tickets']) }}
                    </p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <i class="fas fa-check-circle text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- تیکت‌های خارج از SLA -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">خارج از SLA</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ number_format($stats['missed_sla']) }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ $stats['total_tickets'] > 0 ? round((($stats['total_tickets'] - $stats['missed_sla']) / $stats['total_tickets']) * 100, 2) : 0 }}% رعایت شده
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- میانگین زمان‌ها -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <!-- میانگین زمان اولین پاسخ -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                <i class="fas fa-hourglass-half ml-2"></i>
                میانگین زمان اولین پاسخ
            </h3>
            <div class="text-center">
                <p class="text-4xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['average_first_response_time'] }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">ساعت</p>
            </div>
        </div>

        <!-- میانگین زمان حل مشکل -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                <i class="fas fa-stopwatch ml-2"></i>
                میانگین زمان حل مشکل
            </h3>
            <div class="text-center">
                <p class="text-4xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['average_resolution_time'] }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">ساعت</p>
            </div>
        </div>
    </div>

    <!-- نمودار روند SLA -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-chart-line ml-2"></i>
            روند عملکرد SLA (12 ماه گذشته)
        </h3>
        <canvas id="slaTrendChart" height="80"></canvas>
    </div>

    <!-- عملکرد بر اساس اولویت -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-tasks ml-2"></i>
            عملکرد بر اساس اولویت
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">اولویت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">کل تیکت‌ها</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">رعایت SLA</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">درصد رعایت</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach(['high' => 'بالا', 'normal' => 'عادی', 'low' => 'پایین'] as $priority => $label)
                        @php
                            $priorityStats = $byPriority[$priority] ?? ['total' => 0, 'met_sla' => 0, 'percentage' => 0];
                        @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium
                                    {{ $priority === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' : '' }}
                                    {{ $priority === 'normal' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' : '' }}
                                    {{ $priority === 'low' ? 'bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-200' : '' }}">
                                    {{ $label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ number_format($priorityStats['total']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ number_format($priorityStats['met_sla']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $priorityStats['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $priorityStats['percentage'] }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // نمودار روند SLA
    const slaTrendCtx = document.getElementById('slaTrendChart');
    if (slaTrendCtx) {
        const trends = @json($trends);
        
        new Chart(slaTrendCtx, {
            type: 'line',
            data: {
                labels: trends.map(t => t.month),
                datasets: [
                    {
                        label: 'درصد رعایت SLA',
                        data: trends.map(t => t.percentage),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'تیکت‌های بسته شده',
                        data: trends.map(t => t.total),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'روند عملکرد SLA در 12 ماه گذشته'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'درصد رعایت SLA'
                        },
                        min: 0,
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'تعداد تیکت‌ها'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }
</script>
@endpush
@endsection


