@extends('layouts.admin')

@section('title', 'ساخت عامل جدید - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ساخت عامل جدید')
@section('page-description', 'معمار نجم‌هدا می‌تواند عامل جدید برای شما طراحی کند')

@push('styles')
<style>
    .steps-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }
    
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }
    
    .step-number {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e5e7eb;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.25rem;
        transition: all 0.3s ease;
        margin-bottom: 0.5rem;
    }
    
    .step.active .step-number {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        transform: scale(1.1);
    }
    
    .step.completed .step-number {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .step-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 600;
    }
    
    .step.active .step-label {
        color: #667eea;
        font-weight: 700;
    }
    
    .step.completed .step-label {
        color: #10b981;
    }
    
    .step-line {
        width: 100px;
        height: 3px;
        background: #e5e7eb;
        margin: 0 20px;
        border-radius: 2px;
        transition: all 0.3s ease;
    }
    
    .step.completed ~ .step-line {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .step-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .step-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .step-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
    }
    
    .step-card-header.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .step-card-header.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .form-group-modern {
        margin-bottom: 1.5rem;
    }
    
    .form-label-modern {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
        display: block;
        font-size: 1rem;
    }
    
    .form-control-modern {
        width: 100%;
        padding: 0.875rem 1.25rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        direction: rtl;
        font-family: 'Vazirmatn', sans-serif;
    }
    
    .form-control-modern:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .requirements-container {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .requirement-input {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    .requirement-input input {
        flex: 1;
    }
    
    .requirement-remove {
        padding: 0.5rem;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .requirement-remove:hover {
        background: #dc2626;
        transform: scale(1.05);
    }
    
    .btn-add-requirement {
        padding: 0.5rem 1rem;
        background: #e5e7eb;
        color: #1e293b;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .btn-add-requirement:hover {
        background: #667eea;
        color: white;
    }
    
    .btn-primary-modern {
        padding: 1rem 2rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
    }
    
    .btn-primary-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .btn-primary-modern:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .design-result {
        min-height: 200px;
    }
    
    .design-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: 2px solid #e5e7eb;
    }
    
    .design-card h4 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .design-card code {
        background: #1e293b;
        color: #10b981;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-family: 'Courier New', monospace;
        display: inline-block;
        margin: 0.5rem 0;
    }
    
    .design-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .design-card ul li {
        padding: 0.5rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .design-card ul li:last-child {
        border-bottom: none;
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
    
    .info-box {
        background: #eff6ff;
        border: 2px solid #3b82f6;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .warning-box {
        background: #fef3c7;
        border: 2px solid #f59e0b;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
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
            <h2 class="text-3xl font-bold text-gray-800 mb-2">🏗️ ساخت عامل جدید</h2>
            <p class="text-gray-600">معمار نجم‌هدا می‌تواند عامل جدید برای شما طراحی کند</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <!-- Steps Progress -->
                    <div class="steps-progress">
                        <div class="step active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">توضیح نیاز</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">طراحی</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">بررسی و ذخیره</div>
                        </div>
                    </div>

    <!-- Step 1: Description -->
    <div class="step-card" id="step-1">
        <div class="step-card-header">
            <i class="fas fa-pencil-alt"></i>
            <h3>📝 مرحله 1: توضیح نیاز شما</h3>
        </div>
                    <form id="agent-description-form">
            <div class="form-group-modern">
                <label class="form-label-modern">عامل جدید چه کاری باید انجام دهد؟</label>
                <textarea class="form-control-modern" 
                                      id="agent-description" 
                          rows="6" 
                                      placeholder="مثال: نیاز به عاملی دارم که محتوا و تبلیغات برای شبکه‌های اجتماعی تولید کند، پست‌های اینستاگرام بسازد و به بهینه‌سازی SEO کمک کند."
                                      required></textarea>
                <small class="text-gray-500 mt-1 block">هرچه دقیق‌تر توضیح دهید، طراحی بهتری خواهید داشت</small>
                        </div>

            <div class="form-group-modern">
                <label class="form-label-modern">الزامات خاص (اختیاری)</label>
                <div id="requirements-container" class="requirements-container">
                    <div class="requirement-input">
                        <input type="text" class="form-control-modern" placeholder="مثال: باید از زبان فارسی پشتیبانی کند">
                        <button type="button" class="requirement-remove" onclick="removeRequirement(this)" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                            </div>
                <button type="button" class="btn-add-requirement" onclick="addRequirement()">
                    <i class="fas fa-plus ml-1"></i>
                    افزودن الزام
                            </button>
                        </div>

            <div class="info-box">
                <i class="fas fa-info-circle ml-2"></i>
                            <strong>نکته:</strong> معمار نجم‌هدا ابتدا تحلیل می‌کند که آیا عوامل فعلی می‌توانند این کار را انجام دهند یا خیر.
                        </div>

            <button type="submit" class="btn-primary-modern" id="submit-btn">
                <i class="fas fa-magic"></i>
                شروع طراحی
                        </button>
                    </form>
    </div>

    <!-- Step 2: Design -->
    <div class="step-card hidden" id="step-2">
        <div class="step-card-header success">
            <i class="fas fa-palette"></i>
            <h3>🎨 مرحله 2: طراحی عامل</h3>
                </div>
        <div class="design-result" id="design-result">
            <div class="text-center py-8">
                <div class="spinner"></div>
                <p class="mt-4 text-gray-600">معمار نجم‌هدا در حال طراحی عامل جدید...</p>
            </div>
        </div>
    </div>

    <!-- Step 3: Review and Save -->
    <div class="step-card hidden" id="step-3">
        <div class="step-card-header warning">
            <i class="fas fa-check-circle"></i>
            <h3>✅ مرحله 3: بررسی و ذخیره</h3>
                </div>
        <div class="space-y-4">
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle ml-2"></i>
                        <strong>توجه:</strong> بعد از ذخیره، باید عامل را در Orchestrator و ServiceProvider ثبت کنید.
                    </div>

            <div class="flex flex-col gap-3">
                <button type="button" class="btn-primary-modern" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);" onclick="saveAgent()">
                    <i class="fas fa-save"></i>
                    ذخیره عامل
                        </button>
                <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold" onclick="resetForm()">
                    <i class="fas fa-redo ml-1"></i>
                    شروع مجدد
                        </button>
            </div>
        </div>
    </div>

    <!-- Integration Guide -->
    <div class="step-card hidden" id="integration-guide">
        <div class="step-card-header success">
            <i class="fas fa-book"></i>
            <h3>📚 راهنمای یکپارچه‌سازی</h3>
                </div>
        <div id="integration-steps" class="design-result">
            <!-- Integration steps will be shown here -->
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
let currentDesign = null;

// افزودن الزام جدید
function addRequirement() {
    const container = document.getElementById('requirements-container');
    const requirementDiv = document.createElement('div');
    requirementDiv.className = 'requirement-input';
    requirementDiv.innerHTML = `
        <input type="text" class="form-control-modern" placeholder="الزام دیگر...">
        <button type="button" class="requirement-remove" onclick="removeRequirement(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(requirementDiv);
    
    // Show remove buttons if more than one requirement
    updateRequirementButtons();
}

// حذف الزام
function removeRequirement(button) {
    button.parentElement.remove();
    updateRequirementButtons();
}

// به‌روزرسانی دکمه‌های حذف
function updateRequirementButtons() {
    const requirements = document.querySelectorAll('.requirement-input');
    requirements.forEach((req, index) => {
        const removeBtn = req.querySelector('.requirement-remove');
        if (requirements.length > 1) {
            removeBtn.style.display = 'block';
        } else {
            removeBtn.style.display = 'none';
        }
    });
}

// تغییر مرحله
function changeStep(step) {
    // Hide all steps
    document.querySelectorAll('[id^="step-"]').forEach(el => {
        if (el.id.startsWith('step-')) {
            el.classList.add('hidden');
        }
    });
    
    // Show current step
    document.getElementById(`step-${step}`).classList.remove('hidden');
    
    // Update progress bar
    document.querySelectorAll('.step').forEach((el, index) => {
        el.classList.remove('active', 'completed');
        if (index + 1 < step) {
            el.classList.add('completed');
        } else if (index + 1 === step) {
            el.classList.add('active');
        }
    });
}

// ارسال فرم توضیح
document.getElementById('agent-description-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const description = document.getElementById('agent-description').value.trim();
    if (!description) {
        alert('لطفاً توضیحات را وارد کنید');
        return;
    }
    
    const requirements = Array.from(document.querySelectorAll('#requirements-container input'))
        .map(input => input.value.trim())
        .filter(val => val !== '');
    
    // Change to step 2
    changeStep(2);
    
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال طراحی...';
    
    try {
        const response = await fetch('{{ route('admin.najm-hoda.design-agent') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ description, requirements })
        });
        
        const data = await response.json();
        
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-magic"></i> شروع طراحی';
        
        if (data.success) {
            currentDesign = data.design;
            displayDesign(data.need_analysis, data.design);
            changeStep(3);
        } else {
            alert('❌ خطا: ' + (data.error || 'خطایی رخ داد'));
            changeStep(1);
        }
        
    } catch (error) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-magic"></i> شروع طراحی';
        alert('❌ خطا در ارتباط با سرور');
        changeStep(1);
    }
});

// نمایش طراحی
function displayDesign(needAnalysis, design) {
    const resultDiv = document.getElementById('design-result');
    
    let html = '<div class="space-y-4">';
    
    // Need Analysis
    if (needAnalysis) {
        html += '<div class="design-card">';
        html += '<h4>📋 تحلیل نیاز:</h4>';
        html += '<div class="info-box">';
        html += '<p>' + escapeHtml(needAnalysis.raw_response || JSON.stringify(needAnalysis, null, 2)) + '</p>';
    html += '</div>';
    html += '</div>';
    }
    
    // Design
    html += '<div class="design-card">';
    html += '<h4>🎨 طراحی عامل:</h4>';
    
    if (design.agent_info) {
        html += '<div class="mb-3">';
        html += '<strong>📦 نام کلاس:</strong> <code>' + escapeHtml(design.agent_info.class_name || 'نامشخص') + '</code><br>';
        html += '<strong>نقش:</strong> ' + escapeHtml(design.agent_info.role || 'نامشخص') + '<br>';
        html += '<strong>نام فارسی:</strong> ' + escapeHtml(design.agent_info.persian_name || 'نامشخص');
        html += '</div>';
    }
    
    if (design.expertise && design.expertise.length > 0) {
        html += '<div class="mb-3">';
        html += '<strong>💼 تخصص‌ها:</strong>';
        html += '<ul>';
        design.expertise.forEach(exp => {
            html += '<li>• ' + escapeHtml(exp) + '</li>';
        });
        html += '</ul>';
        html += '</div>';
    }
    
    if (design.methods && design.methods.length > 0) {
        html += '<div class="mb-3">';
        html += '<strong>🛠️ متدها:</strong>';
        html += '<ul>';
        design.methods.forEach(method => {
            const name = typeof method === 'object' ? method.name : method;
            const desc = typeof method === 'object' ? method.description : '';
            html += '<li>• <code>' + escapeHtml(name) + '</code>' + (desc ? ': ' + escapeHtml(desc) : '') + '</li>';
        });
        html += '</ul>';
        html += '</div>';
    }
    
    html += '</div>';
    
    if (design.raw_response) {
        html += '<div class="design-card">';
        html += '<h4>📄 پاسخ کامل:</h4>';
        html += '<pre style="background: #1e293b; color: #10b981; padding: 1rem; border-radius: 8px; overflow-x: auto; direction: ltr; text-align: left;">' + escapeHtml(design.raw_response) + '</pre>';
        html += '</div>';
    }
    
    html += '</div>';
    
    resultDiv.innerHTML = html;
}

// ذخیره عامل
async function saveAgent() {
    if (!currentDesign) {
        alert('❌ طراحی یافت نشد');
        return;
    }
    
    if (!confirm('آیا مطمئن هستید که می‌خواهید این عامل را ذخیره کنید؟')) {
        return;
    }
    
    try {
        const response = await fetch('{{ route('admin.najm-hoda.save-agent') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ design: currentDesign })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show integration guide
            document.getElementById('integration-guide').classList.remove('hidden');
            document.getElementById('integration-steps').innerHTML = 
                '<pre style="background: #1e293b; color: #10b981; padding: 1.5rem; border-radius: 8px; overflow-x: auto; direction: ltr; text-align: left; white-space: pre-wrap;">' + escapeHtml(data.integration_guide || '') + '</pre>';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'عامل ساخته شد!',
                    html: 'عامل با موفقیت ساخته شد.<br><br><strong>مسیر فایل:</strong><br><code>' + (data.file_path || '') + '</code>',
                    confirmButtonText: 'باشه'
                });
            } else {
                alert('✅ عامل با موفقیت ساخته شد!\n📁 مسیر: ' + (data.file_path || ''));
            }
        } else {
            alert('❌ خطا: ' + (data.error || 'خطایی رخ داد'));
        }
        
    } catch (error) {
        alert('❌ خطا در ذخیره عامل');
        console.error(error);
    }
}

// ریست فرم
function resetForm() {
    if (!confirm('آیا مطمئن هستید که می‌خواهید از اول شروع کنید؟')) {
        return;
    }
    
    currentDesign = null;
    document.getElementById('agent-description-form').reset();
    document.getElementById('requirements-container').innerHTML = `
        <div class="requirement-input">
            <input type="text" class="form-control-modern" placeholder="مثال: باید از زبان فارسی پشتیبانی کند">
            <button type="button" class="requirement-remove" onclick="removeRequirement(this)" style="display: none;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('integration-guide').classList.add('hidden');
    changeStep(1);
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
