@extends('layouts.admin')

@section('title', 'تحلیل‌ها و گزارش‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'تحلیل‌ها و گزارش‌ها')
@section('page-description', 'آمار و تحلیل استفاده از نجم‌هدا')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<style>
    .analytics-stats-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        border-top: 4px solid;
    }
    
    .analytics-stats-card.primary {
        border-top-color: #3b82f6;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .analytics-stats-card.success {
        border-top-color: #10b981;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .analytics-stats-card.warning {
        border-top-color: #f59e0b;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .analytics-stats-card.info {
        border-top-color: #06b6d4;
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        color: white;
    }
    
    .analytics-stats-icon {
        font-size: 2.5rem;
        opacity: 0.8;
        margin-bottom: 1rem;
    }
    
    .analytics-stats-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .analytics-stats-label {
        font-size: 0.875rem;
        opacity: 0.9;
    }
    
    .analytics-chart-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .analytics-chart-header {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .analytics-table-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .analytics-table-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .analytics-table-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .analytics-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .analytics-table thead {
        background: #f9fafb;
    }
    
    .analytics-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .analytics-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .analytics-table tr:hover {
        background-color: #f9fafb;
    }
    
    .agent-name {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        color: #1e293b;
    }
    
    .agent-icon {
        font-size: 1.5rem;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">📊 تحلیل‌ها و گزارش‌ها</h2>
            <p class="text-gray-600">آمار و تحلیل استفاده از نجم‌هدا</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="analytics-stats-card primary">
            <div class="analytics-stats-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div class="analytics-stats-value">{{ number_format($totalInteractions) }}</div>
            <div class="analytics-stats-label">تعاملات کل</div>
        </div>
        
        <div class="analytics-stats-card success">
            <div class="analytics-stats-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="analytics-stats-value">${{ number_format($totalCost, 2) }}</div>
            <div class="analytics-stats-label">هزینه کل</div>
        </div>
        
        <div class="analytics-stats-card warning">
            <div class="analytics-stats-icon">
                <i class="fas fa-code"></i>
            </div>
            <div class="analytics-stats-value">{{ number_format($totalTokens) }}</div>
            <div class="analytics-stats-label">توکن استفاده شده</div>
        </div>
        
        <div class="analytics-stats-card info">
            <div class="analytics-stats-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="analytics-stats-value">{{ number_format($avgResponseTime ?? 0, 0) }}ms</div>
            <div class="analytics-stats-label">میانگین زمان پاسخ</div>
        </div>
    </div>

    <!-- Daily Usage Chart -->
    <div class="analytics-chart-card">
        <div class="analytics-chart-header">
            <i class="fas fa-chart-line"></i>
            📈 استفاده روزانه (30 روز اخیر)
        </div>
        <canvas id="dailyUsageChart" height="80"></canvas>
    </div>

    <!-- Agent Stats Table -->
    <div class="analytics-table-card">
        <div class="analytics-table-header">
            <i class="fas fa-chart-bar"></i>
            <h3>🤖 آمار عوامل</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>عامل</th>
                        <th>تعداد استفاده</th>
                        <th>میانگین توکن</th>
                        <th>هزینه کل</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agentStats as $stat)
                    <tr>
                        <td>
                            <div class="agent-name">
                                <span class="agent-icon">
                                    @if($stat->agent_role === 'engineer')
                                        🔧
                                    @elseif($stat->agent_role === 'pilot')
                                        ✈️
                                    @elseif($stat->agent_role === 'steward')
                                        👨‍✈️
                                    @elseif($stat->agent_role === 'guide')
                                        📖
                                    @elseif($stat->agent_role === 'architect')
                                        🏗️
                                    @else
                                        🌟
                                    @endif
                                </span>
                                <span>
                                    @if($stat->agent_role === 'engineer')
                                        مهندس
                                    @elseif($stat->agent_role === 'pilot')
                                        خلبان
                                    @elseif($stat->agent_role === 'steward')
                                        مهماندار
                                    @elseif($stat->agent_role === 'guide')
                                        راهنما
                                    @elseif($stat->agent_role === 'architect')
                                        معمار
                                    @else
                                        نجم‌هدا
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge info">{{ number_format($stat->total_uses) }}</span>
                        </td>
                        <td>{{ number_format($stat->avg_tokens, 0) }}</td>
                        <td>${{ number_format($stat->total_cost, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-gray-500 py-8">
                            <i class="fas fa-inbox text-4xl mb-2 opacity-50"></i>
                            <p>هیچ آماری یافت نشد</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// نمودار استفاده روزانه
const dailyUsageChartElement = document.getElementById('dailyUsageChart');
if (dailyUsageChartElement) {
    const dailyUsageData = {
        labels: {!! json_encode($dailyUsage->pluck('date')->map(function($date) {
            return \Carbon\Carbon::parse($date)->format('Y/m/d');
        })->toArray()) !!},
        datasets: [
            {
                label: 'تعداد تعاملات',
                data: {!! json_encode($dailyUsage->pluck('count')->toArray()) !!},
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'توکن استفاده شده',
                data: {!! json_encode($dailyUsage->pluck('tokens')->toArray()) !!},
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            },
            {
                label: 'هزینه ($)',
                data: {!! json_encode($dailyUsage->pluck('cost')->toArray()) !!},
                borderColor: 'rgb(245, 158, 11)',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y2'
            }
        ]
    };
    
    new Chart(dailyUsageChartElement, {
        type: 'line',
        data: dailyUsageData,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: false,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                },
                y2: {
                    type: 'linear',
                    display: false,
                    position: 'right',
                    beginAtZero: true,
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

