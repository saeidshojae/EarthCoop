@extends('layouts.admin')

@section('title', 'اسکنر کد - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'اسکنر کد و تحلیلگر هوشمند')
@section('page-description', 'تشخیص خودکار مشکلات و پیشنهاد راه‌حل')

@push('styles')
<style>
    .scanner-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .scanner-icon {
        font-size: 4rem;
        color: #667eea;
        margin-bottom: 1rem;
    }
    
    .scanner-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .scanner-description {
        font-size: 1.125rem;
        color: #64748b;
        margin-bottom: 2rem;
    }
    
    .btn-scan {
        padding: 1rem 3rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1.125rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .btn-scan:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }
    
    .btn-scan:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    
    .issue-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-right: 4px solid;
        transition: all 0.3s ease;
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
        margin-left: 1rem;
    }
    
    .issue-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .issue-message {
        font-size: 0.9375rem;
        color: #4b5563;
        margin-bottom: 0.75rem;
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
    }
    
    .issue-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.75rem;
    }
    
    .issue-line {
        font-size: 0.875rem;
        color: #64748b;
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
    
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
        direction: rtl;
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
    
    .hidden {
        display: none;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">🔍 اسکنر کد و تحلیلگر هوشمند</h2>
            <p class="text-gray-600">تشخیص خودکار مشکلات و پیشنهاد راه‌حل</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <!-- Start Scan Card -->
    <div class="scanner-card" id="start-scan-card">
        <div class="scanner-icon">
            <i class="fas fa-search"></i>
        </div>
        <h3 class="scanner-title">آماده اسکن پروژه</h3>
        <p class="scanner-description">نجم‌هدا کل پروژه را بررسی می‌کند و مشکلات را شناسایی می‌نماید</p>
        <button id="start-scan-btn" class="btn-scan">
            <i class="fas fa-play"></i>
            شروع اسکن
        </button>
    </div>

    <!-- Scan Results -->
    <div id="scan-results" class="hidden">
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-file-code"></i>
                </div>
                <div class="stat-value" id="stat-files">0</div>
                <div class="stat-label">فایل اسکن شده</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value" id="stat-critical">0</div>
                <div class="stat-label">مشکل بحرانی</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value" id="stat-high">0</div>
                <div class="stat-label">مشکل مهم</div>
            </div>
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="stat-value" id="stat-medium">0</div>
                <div class="stat-label">مشکل متوسط</div>
            </div>
        </div>

        <!-- Issues List -->
        <div class="issues-card">
            <div class="issues-header">
                <h3>
                    <i class="fas fa-list"></i>
                    📋 مشکلات یافت شده
                </h3>
            </div>
            <div id="issues-list" class="space-y-4">
                <!-- Issues will be shown here -->
            </div>
        </div>

    </div>

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
                        <div class="text-center py-5">
                            <div class="spinner"></div>
                            <p class="mt-4 text-gray-600">نجم‌هدا در حال تحلیل مشکل و تولید پیشنهاد...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentScanResults = null;

// Start scan
document.getElementById('start-scan-btn').addEventListener('click', async () => {
    const btn = document.getElementById('start-scan-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال اسکن...';

    try {
        const response = await fetch('{{ route('admin.najm-hoda.code-scanner.scan') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        if (!response.ok) {
            throw new Error('خطا در اسکن');
        }

        // Redirect to results page after scan
        const data = await response.json();
        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else {
            alert('❌ خطا در اسکن: ' + (data.message || data.error || 'خطایی رخ داد'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> شروع اسکن';
        }

    } catch (error) {
        alert('❌ خطا: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> شروع اسکن';
    }
});

// Show suggestion for an issue
async function showSuggestion(file, issue) {
    const modalElement = document.getElementById('suggestionModal');
    const modal = new bootstrap.Modal(modalElement);
    const content = document.getElementById('suggestion-content');
    
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner"></div>
            <p class="mt-4 text-gray-600">نجم‌هدا در حال تحلیل مشکل و تولید پیشنهاد...</p>
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
            body: JSON.stringify({
                file_path: file,
                issue: issue
            })
        });

        const data = await response.json();

        if (data.success && data.suggestion && data.suggestion.success) {
            displaySuggestion(data.suggestion, issue);
        } else {
            content.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <i class="fas fa-exclamation-circle text-red-600 ml-2"></i>
                    <strong class="text-red-800">خطا در تولید پیشنهاد:</strong>
                    <p class="text-red-600 mt-2">${data.error || 'خطای ناشناخته'}</p>
                </div>
            `;
        }

    } catch (error) {
        content.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <i class="fas fa-exclamation-circle text-red-600 ml-2"></i>
                <strong class="text-red-800">خطا در ارتباط با سرور:</strong>
                <p class="text-red-600 mt-2">${error.message}</p>
            </div>
        `;
    }
}

// Display suggestion
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
                            <pre>${escapeHtml(suggestion.suggested_code || 'در حال تولید...')}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <i class="fas fa-info-circle text-blue-600 ml-2"></i>
                <strong class="text-blue-800">توجه:</strong>
                <p class="text-blue-700 mt-2">پیشنهادات را با دقت بررسی کرده و تست کنید.</p>
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
