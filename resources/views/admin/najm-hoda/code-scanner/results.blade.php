@extends('layouts.admin')

@section('title', 'نتایج اسکن - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'نتایج اسکن پروژه')
@section('page-description', 'تاریخ اسکن: ' . (isset($results['scanned_at']) && is_object($results['scanned_at']) ? $results['scanned_at']->format('Y/m/d H:i') : (isset($results['scanned_at']) ? $results['scanned_at'] : now()->format('Y/m/d H:i'))))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    .results-title {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .results-date {
        font-size: 1rem;
        color: #64748b;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        border-top: 4px solid;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    
    .stat-card.info {
        border-top-color: #06b6d4;
    }
    
    .stat-card.danger {
        border-top-color: #ef4444;
    }
    
    .stat-card.warning {
        border-top-color: #f59e0b;
    }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.8;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .chart-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
    }
    
    .chart-header {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .issues-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .issues-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .issues-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .severity-filter {
        padding: 0.5rem 1rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .severity-filter:focus {
        outline: none;
        border-color: white;
        background: rgba(255, 255, 255, 0.2);
    }
    
    .file-group {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .file-group:last-child {
        border-bottom: none;
    }
    
    .file-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .file-icon {
        font-size: 1.5rem;
        color: #667eea;
    }
    
    .file-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #6b7280;
        color: white;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .issue-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-right: 4px solid;
        transition: all 0.3s ease;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .issue-card:hover {
        transform: translateX(-5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .issue-card.severity-critical {
        border-right-color: #ef4444;
        background: #fef2f2;
    }
    
    .issue-card.severity-high {
        border-right-color: #f59e0b;
        background: #fffbeb;
    }
    
    .issue-card.severity-medium {
        border-right-color: #f59e0b;
        background: #fef3c7;
    }
    
    .issue-card.severity-low {
        border-right-color: #06b6d4;
        background: #ecfeff;
    }
    
    .issue-icon {
        font-size: 2rem;
        flex-shrink: 0;
    }
    
    .issue-content {
        flex: 1;
    }
    
    .issue-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }
    
    .issue-type {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .issue-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .issue-badge.critical {
        background: #ef4444;
        color: white;
    }
    
    .issue-badge.high {
        background: #f59e0b;
        color: white;
    }
    
    .issue-badge.medium {
        background: #f59e0b;
        color: white;
    }
    
    .issue-badge.low {
        background: #06b6d4;
        color: white;
    }
    
    .issue-line {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.75rem;
    }
    
    .issue-message {
        font-size: 0.9375rem;
        color: #4b5563;
        margin-bottom: 0.75rem;
        line-height: 1.6;
    }
    
    .issue-code {
        background: #1e293b;
        color: #10b981;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        margin-bottom: 0.75rem;
        direction: ltr;
        text-align: left;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .issue-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    
    .btn-suggestion {
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-suggestion:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .success-message {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
        border-radius: 16px;
        padding: 3rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }
    
    .success-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .success-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .success-text {
        font-size: 1.125rem;
        opacity: 0.9;
    }
    
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        direction: rtl;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 1.5rem;
        border: none;
    }
    
    .modal-header h5 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    
    .modal-body {
        padding: 2rem;
    }
    
    .suggestion-content {
        direction: rtl;
    }
    
    .diff-view {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .diff-card {
        border-radius: 12px;
        padding: 1rem;
        border: 2px solid;
    }
    
    .diff-original {
        background: #fef2f2;
        border-color: #fca5a5;
    }
    
    .diff-suggested {
        background: #f0fdf4;
        border-color: #86efac;
    }
    
    .code-block {
        background: #1e293b;
        color: #10b981;
        padding: 1rem;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        overflow-x: auto;
        direction: ltr;
        text-align: left;
        margin-top: 0.5rem;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .spinner {
        border: 4px solid #f3f4f6;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="results-header">
        <div>
            <h2 class="results-title">📊 نتایج اسکن پروژه</h2>
            <p class="results-date">تاریخ اسکن: {{ isset($results['scanned_at']) && is_object($results['scanned_at']) ? $results['scanned_at']->format('Y/m/d H:i') : (isset($results['scanned_at']) ? $results['scanned_at'] : now()->format('Y/m/d H:i')) }}</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.najm-hoda.code-scanner') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-redo"></i>
                اسکن مجدد
            </a>
            <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-arrow-right"></i>
                بازگشت
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-file-code"></i>
                </div>
            <div class="stat-value">{{ $results['scanned_files'] ?? 0 }}</div>
            <div class="stat-label">فایل اسکن شده</div>
            </div>
        <div class="stat-card danger">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
        </div>
            <div class="stat-value">{{ $summary['by_severity']['critical'] ?? 0 }}</div>
            <div class="stat-label">مشکل بحرانی</div>
                </div>
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-value">{{ $summary['by_severity']['high'] ?? 0 }}</div>
            <div class="stat-label">مشکل مهم</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-info-circle"></i>
                </div>
            <div class="stat-value">{{ ($summary['by_severity']['medium'] ?? 0) + ($summary['by_severity']['low'] ?? 0) }}</div>
            <div class="stat-label">مشکل جزئی</div>
        </div>
    </div>

    <!-- Charts -->
    @if(isset($results['issues_found']) && $results['issues_found'] > 0)
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-chart-pie"></i>
                📈 توزیع بر اساس سطح اهمیت
                </div>
                    <canvas id="severityChart"></canvas>
                </div>
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-chart-bar"></i>
                🔍 توزیع بر اساس نوع مشکل
                </div>
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
    @endif

    <!-- Issues List -->
    @if(isset($results['issues_found']) && $results['issues_found'] > 0)
    <div class="issues-card">
        <div class="issues-header">
            <h3>
                <i class="fas fa-list"></i>
                📋 مشکلات یافت شده ({{ $results['issues_found'] }} مورد)
            </h3>
            <select id="severity-filter" class="severity-filter">
                                <option value="">همه</option>
                                <option value="critical">بحرانی</option>
                                <option value="high">مهم</option>
                                <option value="medium">متوسط</option>
                                <option value="low">کم</option>
                            </select>
                        </div>
        <div class="space-y-4">
            @foreach($results['issues'] ?? [] as $file => $fileIssues)
            <div class="file-group">
                <div class="file-header">
                    <i class="fas fa-file-code file-icon"></i>
                    <span>{{ str_replace(base_path(), '', $file) }}</span>
                    <span class="file-badge">{{ count($fileIssues) }} مشکل</span>
                    </div>
                                
                                @foreach($fileIssues as $issue)
                <div class="issue-card severity-{{ $issue['severity'] }}" data-severity="{{ $issue['severity'] }}">
                    <div class="issue-icon">
                                                        @if($issue['severity'] === 'critical')
                            <i class="fas fa-times-circle text-red-600"></i>
                                                        @elseif($issue['severity'] === 'high')
                            <i class="fas fa-exclamation-triangle text-orange-600"></i>
                                                        @elseif($issue['severity'] === 'medium')
                            <i class="fas fa-exclamation-circle text-yellow-600"></i>
                                                        @else
                            <i class="fas fa-info-circle text-blue-600"></i>
                                                        @endif
                                                    </div>
                    <div class="issue-content">
                        <div class="issue-title">
                            <span class="issue-type">{{ $issue['type'] }}</span>
                            <span class="issue-badge {{ $issue['severity'] }}">{{ $issue['severity'] }}</span>
                                                    </div>
                        <div class="issue-line">خط {{ $issue['line'] }}</div>
                        <div class="issue-message">{{ $issue['message'] }}</div>
                        <div class="issue-code">{{ $issue['code'] }}</div>
                        <div class="issue-actions">
                            <button class="btn-suggestion" onclick="getSuggestion('{{ addslashes($file) }}', {{ json_encode($issue) }})">
                                <i class="fas fa-magic"></i>
                                دریافت پیشنهاد
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
    @else
    <div class="success-message">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
            </div>
        <h3 class="success-title">عالی! مشکلی یافت نشد!</h3>
        <p class="success-text">کد شما تمیز و بدون مشکل است. 🎉</p>
        </div>
    @endif

    <!-- Modal for Suggestion -->
    <div class="modal fade" id="suggestionModal" tabindex="-1" aria-labelledby="suggestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="suggestionModalLabel">📝 تحلیل و پیشنهاد نجم‌هدا</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="suggestion-content" class="suggestion-content">
                        <!-- Content will be filled by JavaScript -->
                </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// نمودار سطح اهمیت
const severityChartElement = document.getElementById('severityChart');
if (severityChartElement) {
const severityData = {
    labels: ['بحرانی', 'مهم', 'متوسط', 'کم'],
    datasets: [{
        data: [
                {{ $summary['by_severity']['critical'] ?? 0 }},
                {{ $summary['by_severity']['high'] ?? 0 }},
                {{ $summary['by_severity']['medium'] ?? 0 }},
                {{ $summary['by_severity']['low'] ?? 0 }}
            ],
            backgroundColor: ['#ef4444', '#f59e0b', '#f59e0b', '#06b6d4']
        }]
    };
    
    new Chart(severityChartElement, {
    type: 'doughnut',
    data: severityData,
    options: {
        responsive: true,
            maintainAspectRatio: true,
        plugins: {
                legend: {
                    position: 'bottom'
                }
        }
    }
});
}

// نمودار نوع مشکل
const typeChartElement = document.getElementById('typeChart');
if (typeChartElement) {
const typeData = {
        labels: {!! json_encode(array_keys($summary['by_type'] ?? [])) !!},
    datasets: [{
        label: 'تعداد',
            data: {!! json_encode(array_values($summary['by_type'] ?? [])) !!},
        backgroundColor: '#667eea'
    }]
};

    new Chart(typeChartElement, {
    type: 'bar',
    data: typeData,
    options: {
        responsive: true,
            maintainAspectRatio: true,
        plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
        }
    }
});
}

// فیلتر بر اساس سطح اهمیت
const severityFilter = document.getElementById('severity-filter');
if (severityFilter) {
    severityFilter.addEventListener('change', function() {
    const severity = this.value;
    const issues = document.querySelectorAll('.issue-card');
    
    issues.forEach(issue => {
        if (severity === '' || issue.dataset.severity === severity) {
                issue.style.display = 'flex';
        } else {
            issue.style.display = 'none';
        }
    });
});
}

// دریافت پیشنهاد
async function getSuggestion(file, issue) {
    const modalElement = document.getElementById('suggestionModal');
    const modal = new bootstrap.Modal(modalElement);
    const content = document.getElementById('suggestion-content');
    
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner"></div>
            <p class="mt-4 text-gray-600">نجم‌هدا در حال تحلیل و تولید پیشنهاد...</p>
        </div>
    `;
    
    modal.show();

    try {
        const response = await fetch('{{ route('admin.najm-hoda.code-scanner.suggestion') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ file_path: file, issue: issue })
        });

        const data = await response.json();

        if (data.success && data.suggestion && data.suggestion.success) {
            displaySuggestion(data.suggestion, issue);
        } else {
            content.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <i class="fas fa-exclamation-circle text-red-600 ml-2"></i>
                    <strong class="text-red-800">خطا:</strong>
                    <p class="text-red-600 mt-2">${data.error || 'نتوانستم پیشنهاد بدهم'}</p>
                </div>
            `;
        }

    } catch (error) {
        content.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <i class="fas fa-exclamation-circle text-red-600 ml-2"></i>
                <strong class="text-red-800">خطا در ارتباط:</strong>
                <p class="text-red-600 mt-2">${error.message}</p>
            </div>
        `;
    }
}

// نمایش پیشنهاد
function displaySuggestion(suggestion, issue) {
    const content = `
        <div class="space-y-4">
            <div>
                <h6 class="font-bold text-gray-800 mb-2">🔍 مشکل:</h6>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <strong class="text-yellow-800">${issue.type}</strong> (خط ${issue.line})<br>
                    <p class="text-yellow-700 mt-2">${issue.message}</p>
            </div>
        </div>

            <div>
                <h6 class="font-bold text-gray-800 mb-2">💡 توضیحات:</h6>
                <p class="text-gray-700">${suggestion.explanation || 'در حال تحلیل...'}</p>
        </div>

            <div class="diff-view">
                <div>
                    <h6 class="font-bold text-red-800 mb-2">❌ کد فعلی:</h6>
                    <div class="diff-card diff-original">
                        <div class="code-block">
                            <pre>${escapeHtml(suggestion.original_code || issue.code)}</pre>
                        </div>
                    </div>
                </div>
                <div>
                    <h6 class="font-bold text-green-800 mb-2">✅ کد پیشنهادی:</h6>
                    <div class="diff-card diff-suggested">
                        <div class="code-block">
                            <pre>${escapeHtml(suggestion.suggested_code || '')}</pre>
                        </div>
            </div>
            </div>
        </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <i class="fas fa-info-circle text-blue-600 ml-2"></i>
                <strong class="text-blue-800">نکته:</strong>
                <p class="text-blue-700 mt-2">این پیشنهاد توسط نجم‌هدا تولید شده - لطفاً قبل از اعمال تست کنید.</p>
            </div>
        </div>
    `;

    document.getElementById('suggestion-content').innerHTML = content;
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
