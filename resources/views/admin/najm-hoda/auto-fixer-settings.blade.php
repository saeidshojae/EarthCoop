@extends('layouts.admin')

@section('title', 'تنظیمات Auto-Fixer - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'تنظیمات Auto-Fixer')
@section('page-description', 'کمک خلبان هوشمند - رفع خودکار مشکلات کد')

@push('styles')
<style>
    .auto-fixer-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .auto-fixer-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .auto-fixer-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: #10b981;
        color: white;
    }
    
    .status-badge.inactive {
        background: #6b7280;
        color: white;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .stat-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .stat-card.info {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    }
    
    .stat-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .stat-card.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .stat-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        opacity: 0.9;
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
    
    .form-control-modern:disabled {
        background: #f3f4f6;
        cursor: not-allowed;
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
    
    .toggle-switch:has(input:disabled) .toggle-slider {
        background-color: #d1d5db;
        cursor: not-allowed;
    }
    
    .range-slider {
        width: 100%;
        height: 8px;
        border-radius: 4px;
        background: #e5e7eb;
        outline: none;
        transition: all 0.3s ease;
    }
    
    .range-slider:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .range-value {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .range-value-display {
        font-weight: 700;
        font-size: 1.25rem;
        color: #667eea;
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
    
    .btn-modern:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-modern.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-modern.primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .btn-modern.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .btn-modern.success:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .btn-modern.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .btn-modern.info {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        color: white;
    }
    
    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .logs-table thead {
        background: #f9fafb;
    }
    
    .logs-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .logs-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .logs-table tr:hover {
        background-color: #f9fafb;
    }
    
    .badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge.success {
        background: #10b981;
        color: white;
    }
    
    .badge.warning {
        background: #f59e0b;
        color: white;
    }
    
    .badge.danger {
        background: #ef4444;
        color: white;
    }
    
    .badge.info {
        background: #06b6d4;
        color: white;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">⚙️ تنظیمات Auto-Fixer</h2>
            <p class="text-gray-600">کمک خلبان هوشمند - رفع خودکار مشکلات کد</p>
        </div>
        <div class="flex items-center gap-3">
            <span id="statusBadge" class="status-badge inactive">
                <i class="fas fa-circle"></i>
                <span id="statusText">در حال بارگذاری...</span>
            </span>
            <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-arrow-right"></i>
                بازگشت
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="totalFixes">0</div>
                <div class="stat-label">تعداد رفع خودکار</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="totalBackups">0</div>
                <div class="stat-label">تعداد Backup</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-hdd"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="backupSize">0 MB</div>
                <div class="stat-label">حجم Backup</div>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="oldestBackup" style="font-size: 1rem;">-</div>
                <div class="stat-label">قدیمی‌ترین Backup</div>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="auto-fixer-card">
        <div class="auto-fixer-header">
            <h3>
                <i class="fas fa-cog"></i>
                ⚙️ تنظیمات Auto-Fixer
            </h3>
        </div>
        <form id="autoFixerSettings">
            @csrf
            
            <!-- Enable/Disable -->
            <div class="form-group-modern">
                <div class="flex items-center justify-between mb-2">
                    <label class="form-label-modern">
                        <i class="fas fa-power-off text-red-600 ml-2"></i>
                        وضعیت Auto-Fixer
                    </label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="enabledSwitch">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <small class="text-gray-500">با فعال کردن این گزینه، نجم‌هدا می‌تواند مشکلات کد را به صورت خودکار رفع کند</small>
            </div>

            <hr class="my-6">

            <!-- Automation Level -->
            <div class="form-group-modern">
                <label class="form-label-modern">
                    <i class="fas fa-sliders-h text-blue-600 ml-2"></i>
                    سطح اتوماسیون
                </label>
                <select class="form-control-modern" id="levelSelect" disabled>
                    <option value="off">🔴 خاموش - هیچ تغییری اعمال نمی‌شود</option>
                    <option value="safe">🟢 ایمن - فقط فرمت‌بندی و کدهای زائد</option>
                    <option value="moderate">🟡 متوسط - شامل بهینه‌سازی‌های ساده</option>
                    <option value="aggressive">🔴 پیشرفته - اکثر مشکلات (نیاز به تأیید)</option>
                </select>
                <small class="text-gray-500 mt-1 block">
                    <strong>ایمن:</strong> Long Line, Commented Code, Debug Code<br>
                    <strong>متوسط:</strong> ایمن + Inefficient Count, Query in Loop<br>
                    <strong>پیشرفته:</strong> متوسط + N+1 Query, SQL Injection (با تأیید)
                </small>
            </div>

            <!-- Max Fixes Per Run -->
            <div class="form-group-modern">
                <label class="form-label-modern">
                    <i class="fas fa-tachometer-alt text-yellow-600 ml-2"></i>
                    حداکثر رفع در هر اجرا
                </label>
                <input type="range" class="range-slider" id="maxFixesRange" min="1" max="50" value="10" disabled>
                <div class="range-value">
                    <small>1</small>
                    <strong class="range-value-display" id="maxFixesValue">10</strong>
                    <small>50</small>
                </div>
                <small class="text-gray-500">برای جلوگیری از تغییرات ناخواسته، تعداد رفع خودکار محدود شده است</small>
            </div>

            <!-- Require Approval -->
            <div class="form-group-modern">
                <div class="flex items-center justify-between mb-2">
                    <label class="form-label-modern">
                        <i class="fas fa-user-check text-blue-600 ml-2"></i>
                        نیاز به تأیید دستی
                    </label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="requireApproval" disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <small class="text-gray-500">همه رفع‌ها باید توسط ادمین تأیید شوند</small>
                <small class="text-gray-500 block mt-1">توصیه می‌شود در سطوح پیشرفته این گزینه فعال باشد</small>
            </div>

            <!-- Backup Retention -->
            <div class="form-group-modern">
                <label class="form-label-modern">
                    <i class="fas fa-calendar-alt text-green-600 ml-2"></i>
                    مدت نگهداری Backup
                </label>
                <select class="form-control-modern" id="backupRetention" disabled>
                    <option value="7">7 روز</option>
                    <option value="14">14 روز</option>
                    <option value="30" selected>30 روز</option>
                    <option value="60">60 روز</option>
                    <option value="90">90 روز</option>
                </select>
                <small class="text-gray-500">Backup های قدیمی‌تر از این مدت به صورت خودکار حذف می‌شوند</small>
            </div>

            <hr class="my-6">

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-modern primary" disabled id="saveBtn">
                    <i class="fas fa-save"></i>
                    ذخیره تنظیمات
                </button>
                <button type="button" class="btn-modern success" id="testBtn" disabled>
                    <i class="fas fa-vial"></i>
                    تست اجرا
                </button>
                <button type="button" class="btn-modern warning" id="cleanBackupsBtn">
                    <i class="fas fa-broom"></i>
                    پاکسازی Backup های قدیمی
                </button>
                <button type="button" class="btn-modern info" id="viewLogsBtn">
                    <i class="fas fa-history"></i>
                    مشاهده تاریخچه
                </button>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="auto-fixer-card">
        <div class="auto-fixer-header">
            <h3>
                <i class="fas fa-history"></i>
                📜 تاریخچه رفع خودکار
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>فایل</th>
                        <th>نوع مشکل</th>
                        <th>سطح</th>
                        <th>وضعیت</th>
                        <th>Backup ID</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr>
                        <td colspan="7" class="text-center text-gray-500 py-8">
                            <i class="fas fa-inbox text-4xl mb-2 opacity-50"></i>
                            <p>هنوز تغییری اعمال نشده است</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enabledSwitch = document.getElementById('enabledSwitch');
    const switchLabel = document.getElementById('statusText');
    const statusBadge = document.getElementById('statusBadge');
    const levelSelect = document.getElementById('levelSelect');
    const maxFixesRange = document.getElementById('maxFixesRange');
    const maxFixesValue = document.getElementById('maxFixesValue');
    const requireApproval = document.getElementById('requireApproval');
    const backupRetention = document.getElementById('backupRetention');
    const saveBtn = document.getElementById('saveBtn');
    const testBtn = document.getElementById('testBtn');

    // Load settings
    loadSettings();

    // Toggle enabled/disabled
    enabledSwitch.addEventListener('change', function() {
        const isEnabled = this.checked;
        switchLabel.textContent = isEnabled ? 'فعال' : 'غیرفعال';
        
        // Enable/disable fields
        levelSelect.disabled = !isEnabled;
        maxFixesRange.disabled = !isEnabled;
        requireApproval.disabled = !isEnabled;
        backupRetention.disabled = !isEnabled;
        saveBtn.disabled = !isEnabled;
        testBtn.disabled = !isEnabled;

        // Update badge
        if (isEnabled) {
            statusBadge.classList.remove('inactive');
            statusBadge.classList.add('active');
        } else {
            statusBadge.classList.remove('active');
            statusBadge.classList.add('inactive');
        }

        if (!isEnabled) {
            levelSelect.value = 'off';
        }
    });

    // Update range value
    maxFixesRange.addEventListener('input', function() {
        maxFixesValue.textContent = this.value;
    });

    // Load settings
    function loadSettings() {
        fetch('{{ route('admin.najm-hoda.auto-fixer.settings.get') }}')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Settings
                    enabledSwitch.checked = data.settings.enabled;
                    levelSelect.value = data.settings.level;
                    maxFixesRange.value = data.settings.max_fixes_per_run;
                    maxFixesValue.textContent = data.settings.max_fixes_per_run;
                    requireApproval.checked = data.settings.require_approval;
                    backupRetention.value = data.settings.backup_retention_days;

                    // Stats
                    document.getElementById('totalFixes').textContent = data.stats.total_fixes || 0;
                    document.getElementById('totalBackups').textContent = data.stats.total_backups || 0;
                    document.getElementById('backupSize').textContent = data.stats.total_size_mb + ' MB';
                    document.getElementById('oldestBackup').textContent = data.stats.oldest_backup 
                        ? new Date(data.stats.oldest_backup).toLocaleDateString('fa-IR')
                        : '-';

                    // Trigger change event
                    enabledSwitch.dispatchEvent(new Event('change'));
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری تنظیمات:', error);
            });
    }

    // Save settings
    document.getElementById('autoFixerSettings').addEventListener('submit', function(e) {
        e.preventDefault();

        const settings = {
            enabled: enabledSwitch.checked,
            level: levelSelect.value,
            max_fixes_per_run: parseInt(maxFixesRange.value),
            require_approval: requireApproval.checked,
            backup_retention_days: parseInt(backupRetention.value)
        };

        fetch('{{ route('admin.najm-hoda.auto-fixer.settings.save') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(settings)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'تنظیمات ذخیره شد!',
                        text: 'تنظیمات با موفقیت ذخیره شدند.'
                    });
                } else {
                    alert('✅ تنظیمات با موفقیت ذخیره شد');
                }
                loadSettings();
            }
        })
        .catch(error => {
            console.error('خطا در ذخیره:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا در ذخیره',
                    text: 'خطایی در ذخیره تنظیمات رخ داد.'
                });
            } else {
                alert('❌ خطا در ذخیره تنظیمات');
            }
        });
    });

    // Test run
    testBtn.addEventListener('click', function() {
        if (!confirm('آیا می‌خواهید یک تست اجرا انجام دهید؟\nاین عملیات بدون تغییر واقعی در فایل‌ها انجام می‌شود.')) {
            return;
        }

        fetch('{{ route('admin.najm-hoda.auto-fixer.test') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'تست موفق',
                        html: `تعداد مشکلات قابل رفع: <strong>${data.fixable_count}</strong><br>سطح: <strong>${data.level}</strong>`
                    });
                } else {
                    alert(`✅ تست موفق\n\nتعداد مشکلات قابل رفع: ${data.fixable_count}\nسطح: ${data.level}`);
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا در تست',
                        text: data.message || 'خطایی رخ داد'
                    });
                } else {
                    alert('❌ خطا: ' + (data.message || 'خطایی رخ داد'));
                }
            }
        })
        .catch(error => {
            console.error('خطا در تست:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا در تست',
                    text: error.message
                });
            } else {
                alert('❌ خطا در تست');
            }
        });
    });

    // Clean backups
    document.getElementById('cleanBackupsBtn').addEventListener('click', function() {
        if (!confirm('آیا می‌خواهید Backup های قدیمی را پاک کنید؟')) {
            return;
        }

        fetch('{{ route('admin.najm-hoda.auto-fixer.clean-backups') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup های قدیمی پاک شدند',
                        text: `${data.deleted_count} Backup پاک شد`
                    });
                } else {
                    alert(`✅ ${data.deleted_count} Backup پاک شد`);
                }
                loadSettings();
            }
        })
        .catch(error => {
            console.error('خطا:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: error.message
                });
            } else {
                alert('❌ خطا');
            }
        });
    });

    // View logs
    document.getElementById('viewLogsBtn').addEventListener('click', function() {
        loadLogs();
    });

    // Load logs
    function loadLogs() {
        fetch('{{ route('admin.najm-hoda.auto-fixer.logs') }}')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('logsTableBody');
                
                if (data.success && data.logs && data.logs.length > 0) {
                    tbody.innerHTML = data.logs.map(log => `
                        <tr>
                            <td>${new Date(log.fixed_at || log.created_at).toLocaleString('fa-IR')}</td>
                            <td><code>${log.file ? log.file.substring(log.file.lastIndexOf('/') + 1) : 'نامشخص'}</code></td>
                            <td><span class="badge info">${log.issue_type || 'نامشخص'}</span></td>
                            <td><span class="badge ${getSeverityBadge(log.level)}">${log.level || 'نامشخص'}</span></td>
                            <td><span class="badge ${log.status === 'success' ? 'success' : 'danger'}">${log.status || 'نامشخص'}</span></td>
                            <td><small>${log.backup_id ? log.backup_id.substring(0, 8) + '...' : '-'}</small></td>
                            <td>
                                <button class="btn-modern warning" style="padding: 0.5rem 1rem; font-size: 0.875rem;" onclick="rollback('${log.backup_id}')">
                                    <i class="fas fa-undo"></i>
                                    بازگردانی
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-gray-500 py-8">
                                <i class="fas fa-inbox text-4xl mb-2 opacity-50"></i>
                                <p>تاریخچه‌ای یافت نشد</p>
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری تاریخچه:', error);
            });
    }

    function getSeverityBadge(level) {
        const badges = {
            'safe': 'success',
            'moderate': 'warning',
            'aggressive': 'danger'
        };
        return badges[level] || 'info';
    }

    // Rollback
    window.rollback = function(backupId) {
        if (!confirm('آیا می‌خواهید این تغییر را بازگردانی کنید؟')) {
            return;
        }

        fetch('{{ route('admin.najm-hoda.auto-fixer.rollback') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ backup_id: backupId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'فایل بازگردانی شد',
                        text: 'فایل با موفقیت بازگردانی شد'
                    });
                } else {
                    alert('✅ فایل با موفقیت بازگردانی شد');
                }
                loadLogs();
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا در بازگردانی',
                        text: data.error || 'خطایی رخ داد'
                    });
                } else {
                    alert('❌ خطا: ' + (data.error || 'خطایی رخ داد'));
                }
            }
        })
        .catch(error => {
            console.error('خطا در بازگردانی:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا در بازگردانی',
                    text: error.message
                });
            } else {
                alert('❌ خطا در بازگردانی');
            }
        });
    };
});
</script>
@endpush
