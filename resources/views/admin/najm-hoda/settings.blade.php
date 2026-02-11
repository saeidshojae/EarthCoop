@extends('layouts.admin')

@section('title', 'تنظیمات نجم‌هدا - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'تنظیمات نجم‌هدا')
@section('page-description', 'پیکربندی سیستم هوش مصنوعی')

@push('styles')
<style>
    .settings-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .settings-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .settings-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
    }
    
    .form-group-modern {
        margin-bottom: 1.5rem;
    }
    
    .form-label-modern {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-control-modern {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        direction: rtl;
    }
    
    .form-control-modern:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider {
        background-color: #667eea;
    }
    
    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
    
    .agent-settings-item {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 2px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    
    .agent-settings-item:hover {
        border-color: #667eea;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .agent-advanced summary {
        cursor: pointer;
        color: #2563eb;
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .agent-advanced summary::-webkit-details-marker {
        display: none;
    }
    
    .agent-advanced[open] summary {
        color: #1d4ed8;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .status-badge.success {
        background: #10b981;
        color: white;
    }
    
    .status-badge.warning {
        background: #f59e0b;
        color: white;
    }
    
    .status-badge.danger {
        background: #ef4444;
        color: white;
    }
    
    .info-box {
        background: #eff6ff;
        border: 2px solid #3b82f6;
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .warning-box {
        background: #fef3c7;
        border: 2px solid #f59e0b;
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .btn-modern {
        padding: 0.75rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-modern.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-modern.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .btn-modern.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .btn-modern.info {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        color: white;
    }
    
    .btn-modern.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    /* Additional fix for upload form */
    #steward-upload-status.hidden {
        display: none !important;
    }
    
    #steward-upload-status {
        margin-top: 8px;
        font-size: 12px;
        padding: 8px;
        border-radius: 6px;
    }
    
    #steward-upload-status.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }
    
    #steward-upload-status.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #ef4444;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">⚙️ تنظیمات نجم‌هدا</h2>
            <p class="text-gray-600">پیکربندی سیستم هوش مصنوعی</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- تنظیمات عمومی -->
        <div class="settings-card">
            <div class="settings-card-header">
                <i class="fas fa-cog"></i>
                <h3>🔧 تنظیمات عمومی</h3>
                </div>
                    <form id="general-settings-form">
                        @csrf
                        
                <div class="form-group-modern">
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label-modern">فعال بودن نجم‌هدا</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="najm-hoda-enabled" {{ config('najm-hoda.enabled') ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                                </label>
                            </div>
                    <small class="text-gray-500">فعال/غیرفعال کردن کل سیستم</small>
                        </div>

                <div class="form-group-modern">
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label-modern">Mock Mode (حالت شبیه‌سازی)</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="mock-mode" {{ config('najm-hoda.mock_mode') ? 'checked' : '' }} onchange="toggleMockModeInfo()">
                            <span class="toggle-slider"></span>
                                </label>
                            </div>
                    <small class="text-gray-500">برای تست بدون استفاده از API واقعی</small>
                            
                    <div id="mock-mode-info" class="info-box {{ config('najm-hoda.mock_mode') ? '' : 'hidden' }}">
                                <i class="fas fa-info-circle"></i>
                                <strong>Mock Mode فعال است</strong>
                        <p class="mb-0 text-sm mt-1">در این حالت، پاسخ‌های از پیش تعریف شده استفاده می‌شود و هیچ درخواستی به OpenAI ارسال نمی‌شود.</p>
                            </div>
                        </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">ارائه‌دهنده AI</label>
                    <select class="form-control-modern" id="ai-provider">
                                <option value="openai" {{ config('najm-hoda.provider.type') == 'openai' ? 'selected' : '' }}>OpenAI</option>
                                <option value="openrouter" {{ config('najm-hoda.provider.type') == 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                                <option value="claude" {{ config('najm-hoda.provider.type') == 'claude' ? 'selected' : '' }}>Claude (Anthropic)</option>
                                <option value="gemini" {{ config('najm-hoda.provider.type') == 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                            </select>
                        </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">مدل AI</label>
                    <input type="text" class="form-control-modern" id="ai-model" value="{{ config('najm-hoda.provider.model') }}">
                        </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">API Key</label>
                    <div class="flex gap-2">
                        <input type="password" class="form-control-modern flex-1" id="ai-api-key" value="{{ config('najm-hoda.provider.api_key') ? '***********' : '' }}">
                        <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors" onclick="toggleApiKey()">
                            <i class="fas fa-eye" id="api-key-icon"></i>
                                </button>
                            </div>
                        </div>

                <button type="submit" class="btn-modern primary w-full justify-center">
                    <i class="fas fa-save"></i>
                    ذخیره تنظیمات
                        </button>
                    </form>
        </div>

        <!-- تنظیمات عوامل -->
        <div class="settings-card">
            <div class="settings-card-header" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                <i class="fas fa-robot"></i>
                <h3>🤖 تنظیمات عوامل</h3>
                </div>
                    
                    @php
                    $agents = [
                        'engineer' => ['name' => 'مهندس', 'icon' => '🔧'],
                        'pilot' => ['name' => 'خلبان', 'icon' => '✈️'],
                        'steward' => ['name' => 'مهماندار', 'icon' => '👨‍✈️'],
                        'guide' => ['name' => 'راهنما', 'icon' => '📖'],
                        'architect' => ['name' => 'معمار', 'icon' => '🏗️'],
                    ];
                    @endphp

                    @foreach($agents as $key => $agent)
            <div class="agent-settings-item">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">{{ $agent['icon'] }}</span>
                        <div>
                            <div class="font-bold text-gray-800">{{ $agent['name'] }}</div>
                            <small class="text-gray-500">عامل {{ $agent['name'] }}</small>
                        </div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="agent-{{ $key }}" {{ config("najm-hoda.agents.{$key}.enabled", true) ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <details class="agent-advanced">
                    <summary>
                        <i class="fas fa-cog"></i>
                        تنظیمات پیشرفته
                    </summary>
                    <div class="bg-white rounded-lg p-4 border border-gray-200 mt-2">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label-modern text-sm">Temperature</label>
                                <input type="range" class="w-full" min="0" max="1" step="0.1" value="{{ config("najm-hoda.agents.{$key}.temperature", 0.5) }}">
                                <small class="text-gray-500">{{ config("najm-hoda.agents.{$key}.temperature", 0.5) }}</small>
                            </div>
                            <div>
                                <label class="form-label-modern text-sm">Max Tokens</label>
                                <input type="number" class="form-control-modern" value="{{ config("najm-hoda.agents.{$key}.max_tokens", 2000) }}">
                            </div>
                        </div>
                        @if ($key === 'steward')
                            <div class="mt-3 p-3 border border-sky-400 bg-sky-50 rounded-lg">
                                <a href="{{ route('admin.kb.steward.dashboard') }}" class="block text-slate-900 font-bold no-underline">
                                    🔗 داشبورد مهماندار (Steward)
                                </a>
                                <div class="text-xs text-slate-600 mt-2">
                                    مشاهده وضعیت منابع محتوا و یکپارچه‌سازی
                                </div>
                            </div>

                            <div class="mt-3 p-3 border border-green-600 bg-green-50 rounded-lg">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="text-sm font-bold text-green-900 m-0">
                                        📎 آپلود فایل‌های دانش
                                    </h4>
                                    <a href="{{ route('admin.najm-hoda.steward.knowledge-files-page') }}" class="bg-green-700 text-white px-3 py-1.5 rounded-md no-underline text-xs font-bold">
                                        <i class="fas fa-list"></i>
                                        مدیریت فایل‌ها
                                    </a>
                                </div>
                                <div class="bg-yellow-100 border border-yellow-400 p-2.5 rounded-md mb-3 text-xs text-yellow-900">
                                    <strong>⚠️ توجه:</strong> فایل‌های TXT و Markdown محتوا کاملاً استخراج می‌شوند. برای فایل‌های PDF و Word فقط نام فایل برای جستجو استفاده می‌شود.
                                </div>
                                <form id="steward-upload-form" enctype="multipart/form-data">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="text-xs text-green-900 block mb-1">
                                            عنوان فایل:
                                        </label>
                                        <input type="text" name="title" class="form-control-modern text-sm px-2.5 py-1.5" placeholder="عنوان توصیفی برای فایل" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="text-xs text-green-900 block mb-1">
                                            انتخاب فایل:
                                        </label>
                                        <input type="file" name="knowledge_file" id="knowledge_file" class="form-control-modern text-sm px-2.5 py-1.5" accept=".pdf,.doc,.docx,.txt,.md" required>
                                        <small class="text-xs text-green-900">فرمت‌های مجاز: PDF, Word, TXT, Markdown | حداکثر 10MB</small>
                                    </div>
                                    <div class="mb-2">
                                        <label class="text-xs text-green-900 block mb-1">
                                            اولویت جستجو (1-10):
                                        </label>
                                        <input type="number" name="search_priority" class="form-control-modern text-sm px-2.5 py-1.5" min="1" max="10" value="5">
                                    </div>
                                    <button type="submit" class="btn-modern bg-gradient-to-r from-green-600 to-green-800 text-white w-full justify-center text-sm px-2 py-2">
                                        <i class="fas fa-upload"></i>
                                        آپلود فایل
                                    </button>
                                    <div id="steward-upload-status" class="mt-2 text-xs text-green-900 hidden"></div>
                                </form>
                            </div>

                            <div id="uploaded-files-list" class="mt-3"></div>
                        @endif
                    </div>
                </details>
            </div>
            @endforeach

        </div>

        <!-- وضعیت سیستم -->
        <div class="settings-card">
            <div class="settings-card-header" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                <i class="fas fa-chart-bar"></i>
                <h3>📊 وضعیت سیستم</h3>
            </div>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span class="text-gray-700">حالت فعلی:</span>
                    <span id="current-mode" class="status-badge {{ config('najm-hoda.mock_mode') ? 'warning' : 'success' }}">
                        {{ config('najm-hoda.mock_mode') ? 'Mock Mode' : 'Production' }}
                    </span>
                    </div>

                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span class="text-gray-700">ارائه‌دهنده:</span>
                    <strong class="text-gray-800">{{ config('najm-hoda.provider.type') }}</strong>
                    </div>

                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span class="text-gray-700">مدل:</span>
                    <strong class="text-gray-800">{{ config('najm-hoda.provider.model') }}</strong>
                    </div>

                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span class="text-gray-700">عوامل فعال:</span>
                    <strong class="text-gray-800">
                                @php
                                $activeAgents = collect($agents)->filter(function($agent, $key) {
                                    return config("najm-hoda.agents.{$key}.enabled", true);
                                })->count();
                                @endphp
                                {{ $activeAgents }} / {{ count($agents) }}
                            </strong>
                    </div>

                <div class="flex flex-col gap-2 mt-4">
                    <button class="btn-modern warning w-full justify-center" onclick="testConnection()">
                        <i class="fas fa-plug"></i>
                        تست اتصال API
                        </button>
                    <button class="btn-modern info w-full justify-center" onclick="clearCache()">
                        <i class="fas fa-broom"></i>
                        پاک کردن Cache
                        </button>
                    <button class="btn-modern danger w-full justify-center" onclick="resetSettings()">
                        <i class="fas fa-undo"></i>
                        بازگشت به تنظیمات پیش‌فرض
                        </button>
                </div>
            </div>
        </div>

        <!-- راهنمای Mock Mode -->
        <div class="settings-card" style="border: 2px solid #f59e0b;">
            <div class="settings-card-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-book"></i>
                <h3>📘 راهنمای Mock Mode</h3>
                </div>
            
            <div>
                <h4 class="font-bold text-gray-800 mb-2">چرا از Mock Mode استفاده کنیم؟</h4>
                <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 mb-4">
                        <li>تست سیستم بدون نیاز به API واقعی</li>
                        <li>صرفه‌جویی در هزینه‌های API</li>
                        <li>سرعت بالاتر در محیط Development</li>
                        <li>قابلیت تست بدون اتصال به اینترنت</li>
                    </ul>

                <h4 class="font-bold text-gray-800 mb-2">محدودیت‌ها:</h4>
                <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 mb-4">
                        <li>پاسخ‌ها از پیش تعریف شده هستند</li>
                        <li>قابلیت یادگیری و سفارشی‌سازی ندارد</li>
                        <li>فقط برای تست و توسعه مناسب است</li>
                    </ul>

                <div class="warning-box">
                        <i class="fas fa-lightbulb"></i>
                        <strong>توصیه:</strong> در محیط Production حتماً Mock Mode را غیرفعال کنید.
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
function toggleMockModeInfo() {
    const mockMode = document.getElementById('mock-mode').checked;
    const infoBox = document.getElementById('mock-mode-info');
    
    if (mockMode) {
        infoBox.classList.remove('hidden');
    } else {
        infoBox.classList.add('hidden');
    }
}

function toggleApiKey() {
    const input = document.getElementById('ai-api-key');
    const icon = document.getElementById('api-key-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

async function testConnection() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'در حال تست اتصال...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    } else {
    alert('در حال تست اتصال...');
    }
    
    try {
        const response = await fetch('/admin/najm-hoda/test-connection', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (typeof Swal !== 'undefined') {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'اتصال موفقیت‌آمیز بود!',
                    text: 'سیستم به درستی کار می‌کند.'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا در اتصال',
                    text: data.message || 'خطایی رخ داد'
                });
            }
        } else {
        if (data.success) {
            alert('✅ اتصال موفقیت‌آمیز بود!');
        } else {
            alert('❌ خطا: ' + data.message);
            }
        }
    } catch (error) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'خطا در ارتباط با سرور',
                text: error.message
            });
        } else {
        alert('❌ خطا در ارتباط با سرور');
        }
    }
}

function clearCache() {
    if (confirm('آیا مطمئن هستید که می‌خواهید Cache را پاک کنید؟')) {
        // در اینجا باید API call برای پاک کردن cache انجام شود
        alert('Cache پاک شد!');
        location.reload();
    }
}

function resetSettings() {
    if (confirm('تمام تنظیمات به حالت پیش‌فرض بازگردانده می‌شود. آیا مطمئن هستید؟')) {
        // در اینجا باید API call برای reset settings انجام شود
        alert('تنظیمات بازگردانده شد!');
        location.reload();
    }
}

// ذخیره تنظیمات
document.getElementById('general-settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const settings = {
        enabled: document.getElementById('najm-hoda-enabled').checked,
        mock_mode: document.getElementById('mock-mode').checked,
        provider: document.getElementById('ai-provider').value,
        model: document.getElementById('ai-model').value,
        api_key: document.getElementById('ai-api-key').value,
    };
    
    try {
        const response = await fetch('{{ route('admin.najm-hoda.settings.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(settings)
        });
        
        const data = await response.json();
        
        if (data.success || response.ok) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'تنظیمات ذخیره شد!',
                    text: 'تنظیمات با موفقیت ذخیره شدند.'
                }).then(() => {
                    location.reload();
                });
            } else {
            alert('✅ تنظیمات با موفقیت ذخیره شد!');
            location.reload();
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا در ذخیره',
                    text: data.message || 'خطایی رخ داد'
                });
            } else {
                alert('❌ خطا: ' + (data.message || 'خطایی رخ داد'));
            }
        }
    } catch (error) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'خطا در ذخیره تنظیمات',
                text: error.message
            });
        } else {
        alert('❌ خطا در ذخیره تنظیمات');
        }
    }
});

// آپلود فایل دانش Steward - با DOMContentLoaded برای اطمینان از لود شدن
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Checking for steward upload form...');
    const uploadForm = document.getElementById('steward-upload-form');
    
    if (!uploadForm) {
        console.log('❌ Upload form not found - احتمالاً کارت بسته است');
        return;
    }
    
    console.log('✅ Upload form found!');
    
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        console.log('📤 Form submitted!');
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        const formData = new FormData(form);
        
        const statusBox = document.getElementById('steward-upload-status');
        const showStatus = (message, isError = false) => {
            if (!statusBox) return;
            statusBox.classList.remove('hidden', 'success', 'error');
            statusBox.classList.add(isError ? 'error' : 'success');
            statusBox.textContent = message;
            statusBox.style.display = 'block';
        };
        
        // بررسی فایل انتخاب شده است یا نه
        const fileInput = form.querySelector('input[name="knowledge_file"]');
        if (!fileInput.files.length) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'هشدار',
                    text: 'لطفا فایلی را برای آپلود انتخاب کنید'
                });
            } else {
                alert('⚠️ لطفا فایلی را برای آپلود انتخاب کنید');
            }
            showStatus('لطفاً یک فایل انتخاب کنید', true);
            return;
        }
        
        // بررسی اندازه فایل
        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'فایل خیلی بزرگ است',
                    text: 'حداکثر اندازه فایل 10 مگابایت است'
                });
            } else {
                alert('❌ حداکثر اندازه فایل 10 مگابایت است');
            }
            showStatus('حداکثر اندازه: 10MB', true);
            return;
        }
        
        // تنظیم loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال آپلود...';
        showStatus('درحال ارسال فایل...');
        
        try {
            console.log('📤 Uploading:', file.name, '(' + (file.size / 1024).toFixed(2) + ' KB)');
            
            const response = await fetch('{{ route('admin.najm-hoda.steward.upload-knowledge') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData,
                credentials: 'same-origin'
            });
            
            let data = null;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const rawText = await response.text();
                console.error('❌ Non-JSON response:', rawText.slice(0, 300));
                throw new Error('پاسخ غیرمنتظره از سرور. کد: ' + response.status);
            }

            console.log('📥 Server response:', data);

            if (response.ok && data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '✅ آپلود موفق',
                        text: data.message,
                        confirmButtonText: 'باشه'
                    }).then(() => {
                        form.reset();
                        loadKnowledgeFiles();
                    });
                } else {
                    alert('✅ ' + data.message);
                    form.reset();
                    loadKnowledgeFiles();
                }
                showStatus(data.message || 'آپلود موفق!');
            } else {
                const errorMessage = data?.message || 'خطای نامشخص';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: '❌ خطا در آپلود',
                        text: errorMessage,
                        confirmButtonText: 'باشه'
                    });
                } else {
                    alert('❌ ' + errorMessage);
                }
                showStatus(errorMessage, true);
            }
        } catch (error) {
            console.error('💥 Upload error:', error);
            const errorMessage = 'خطا: ' + (error.message || 'نامشخص');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: '❌ خطا',
                    text: errorMessage,
                    confirmButtonText: 'باشه'
                });
            } else {
                alert('❌ ' + errorMessage);
            }
            showStatus(errorMessage, true);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
    
    // بارگذاری اولیه لیست
    if (document.getElementById('uploaded-files-list')) {
        loadKnowledgeFiles();
    }
});

// بارگذاری لیست فایل‌های دانش
async function loadKnowledgeFiles() {
    try {
        console.log('Loading knowledge files from:', '/admin/najm-hoda/steward/knowledge-files-api');
        const response = await fetch('/admin/najm-hoda/steward/knowledge-files-api');
        const data = await response.json();
        
        console.log('Knowledge files response:', data);
        
        if (data.success && data.files && data.files.length > 0) {
            const listHtml = `
                <div style="padding: 12px; border: 1px solid #e5e7eb; background: #f9fafb; border-radius: 8px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 12px;">
                        📚 فایل‌های آپلودشده (${data.files.length})
                    </h4>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                            <thead style="background: #f3f4f6; position: sticky; top: 0;">
                                <tr>
                                    <th style="padding: 8px; text-align: right; border-bottom: 2px solid #e5e7eb;">عنوان</th>
                                    <th style="padding: 8px; text-align: center; border-bottom: 2px solid #e5e7eb;">نوع</th>
                                    <th style="padding: 8px; text-align: center; border-bottom: 2px solid #e5e7eb;">حجم</th>
                                    <th style="padding: 8px; text-align: center; border-bottom: 2px solid #e5e7eb;">اولویت</th>
                                    <th style="padding: 8px; text-align: center; border-bottom: 2px solid #e5e7eb;">آپلودکننده</th>
                                    <th style="padding: 8px; text-align: center; border-bottom: 2px solid #e5e7eb;">تاریخ</th>
                                    <th style="padding: 8px; text-align: center; border-bottom: 2px solid #e5e7eb; width: 100px;">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.files.map(file => `
                                    <tr style="border-bottom: 1px solid #e5e7eb;" id="file-row-${file.id}">
                                        <td style="padding: 8px;">
                                            <i class="fas ${file.icon}" style="color: #6366f1; margin-left: 4px;"></i>
                                            <span id="file-title-${file.id}">${file.title}</span>
                                        </td>
                                        <td style="padding: 8px; text-align: center;">
                                            <span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 11px;">
                                                ${file.file_type.toUpperCase()}
                                            </span>
                                        </td>
                                        <td style="padding: 8px; text-align: center; color: #6b7280;">${file.file_size}</td>
                                        <td style="padding: 8px; text-align: center;">
                                            <span id="file-priority-${file.id}" style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-weight: 700;">
                                                ${file.search_priority}
                                            </span>
                                        </td>
                                        <td style="padding: 8px; text-align: center; color: #6b7280;">${file.uploader}</td>
                                        <td style="padding: 8px; text-align: center; color: #6b7280; font-size: 11px;">${file.created_at}</td>
                                        <td style="padding: 8px; text-align: center;">
                                            <button onclick="editKnowledgeFile(${file.id}, ${JSON.stringify(file.title)}, ${file.search_priority})" 
                                                    style="border: none; background: #3b82f6; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-left: 4px; font-size: 11px;"
                                                    title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteKnowledgeFile(${file.id})" 
                                                    style="border: none; background: #ef4444; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px;"
                                                    title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            document.getElementById('uploaded-files-list').innerHTML = listHtml;
        } else {
            document.getElementById('uploaded-files-list').innerHTML = '<div style="padding: 12px; text-align: center; color: #9ca3af; font-size: 13px;">هیچ فایلی آپلود نشده است</div>';
        }
    } catch (error) {
        console.error('خطا در بارگذاری لیست فایل‌ها:', error);
    }
}

// حذف فایل دانش
async function deleteKnowledgeFile(id) {
    if (!confirm('آیا از حذف این فایل مطمئن هستید?')) return;
    
    try {
        const response = await fetch(`/admin/najm-hoda/steward/knowledge-files/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'حذف شد',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                alert('✅ ' + data.message);
            }
            loadKnowledgeFiles();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('❌ خطا در حذف فایل');
    }
}

// ویرایش فایل دانش
async function editKnowledgeFile(id, currentTitle, currentPriority) {
    const newTitle = prompt('عنوان جدید:', currentTitle);
    if (!newTitle || newTitle === currentTitle) {
        // اگر کنسل کرد یا تغییری نداد، از اولویت بپرس
        const newPriority = prompt('اولویت جستجو (1-10):', currentPriority);
        if (!newPriority) return;
        
        // فقط اولویت تغییر کرده
        await updateKnowledgeFile(id, currentTitle, parseInt(newPriority));
        return;
    }
    
    const newPriority = prompt('اولویت جستجو (1-10):', currentPriority);
    if (!newPriority) return;
    
    await updateKnowledgeFile(id, newTitle, parseInt(newPriority));
}

async function updateKnowledgeFile(id, title, priority) {
    try {
        const response = await fetch(`/admin/najm-hoda/steward/knowledge-files/${id}`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                title: title,
                search_priority: priority
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'ویرایش شد',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                alert('✅ ' + data.message);
            }
            loadKnowledgeFiles();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('❌ خطا در ویرایش فایل');
    }
}
</script>
@endpush
