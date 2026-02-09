@extends('layouts.admin')

@section('title', 'Import کاربران - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'Import کاربران')
@section('page-description', 'Import کاربران از فایل Excel یا CSV')

@push('styles')
<style>
    .import-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .import-header {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .import-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .import-header p {
        color: #64748b;
        font-size: 0.875rem;
    }
    
    .import-form-group {
        margin-bottom: 1.5rem;
    }
    
    .import-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .import-form-label .required {
        color: #ef4444;
    }
    
    .import-file-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px dashed #e5e7eb;
        border-radius: 0.75rem;
        background: #f9fafb;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .import-file-input:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .import-file-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .import-help {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .import-help-title {
        font-weight: 600;
        color: #0369a1;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .import-help-list {
        list-style: none;
        padding: 0;
        margin: 0;
        font-size: 0.875rem;
        color: #0c4a6e;
    }
    
    .import-help-list li {
        padding: 0.25rem 0;
    }
    
    .import-help-list li:before {
        content: "• ";
        color: #3b82f6;
        font-weight: bold;
    }
    
    .import-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }
    
    .import-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .import-btn.submit {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .import-btn.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .import-btn.cancel {
        background: #6b7280;
        color: white;
    }
    
    .import-btn.cancel:hover {
        background: #4b5563;
    }
    
    .import-template-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 0.5rem;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 600;
        margin-top: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .import-template-link:hover {
        background: #c7d2fe;
        color: #4338ca;
    }
    
    @media (prefers-color-scheme: dark) {
        .import-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .import-header {
            border-bottom-color: #475569 !important;
        }
        
        .import-header h3 {
            color: #f1f5f9 !important;
        }
        
        .import-header p {
            color: #cbd5e1 !important;
        }
        
        .import-form-label {
            color: #f1f5f9 !important;
        }
        
        .import-file-input {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .import-help {
            background: #1e3a8a !important;
            border-color: #3b82f6 !important;
        }
        
        .import-help-title {
            color: #bfdbfe !important;
        }
        
        .import-help-list {
            color: #bfdbfe !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="import-card">
        <div class="import-header">
            <h3>
                <i class="fas fa-file-upload ml-2"></i>
                Import کاربران
            </h3>
            <p>فایل Excel یا CSV خود را آپلود کنید تا کاربران به صورت دسته‌ای ایجاد شوند.</p>
        </div>
        
        <div class="import-help">
            <div class="import-help-title">
                <i class="fas fa-info-circle ml-2"></i>
                فرمت فایل
            </div>
            <ul class="import-help-list">
                <li>فایل باید دارای ستون‌های زیر باشد: email, first_name, last_name</li>
                <li>ستون‌های اختیاری: phone, national_id, gender (male/female), password, status (active/inactive/suspended), email_verified (1/0)</li>
                <li>فرمت‌های پشتیبانی شده: .xlsx, .xls, .csv</li>
                <li>حداکثر حجم فایل: 10MB</li>
            </ul>
            <a href="#" class="import-template-link" onclick="downloadTemplate(); return false;">
                <i class="fas fa-download"></i>
                دانلود فایل نمونه
            </a>
        </div>
        
        <form action="{{ route('admin.users.import.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="import-form-group">
                <label for="file" class="import-form-label">
                    انتخاب فایل <span class="required">*</span>
                </label>
                <input type="file" 
                       id="file" 
                       name="file" 
                       class="import-file-input @error('file') border-red-500 @enderror" 
                       accept=".xlsx,.xls,.csv"
                       required>
                @error('file')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            @if(session('import_errors') && count(session('import_errors')) > 0)
            <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 0.75rem; padding: 1rem; margin-bottom: 1.5rem;">
                <div style="font-weight: 600; color: #991b1b; margin-bottom: 0.5rem;">
                    خطاهای Import ({{ count(session('import_errors')) }} ردیف)
                </div>
                <div style="max-height: 200px; overflow-y: auto; font-size: 0.875rem; color: #7f1d1d;">
                    @foreach(session('import_errors') as $error)
                    <div style="padding: 0.5rem 0; border-bottom: 1px solid #fecaca;">
                        <strong>ردیف:</strong> {{ json_encode($error['row'], JSON_UNESCAPED_UNICODE) }}<br>
                        <strong>خطاها:</strong> {{ implode(', ', $error['errors']) }}
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            <div class="import-actions">
                <a href="{{ route('admin.users.index') }}" class="import-btn cancel">
                    <i class="fas fa-times"></i>
                    انصراف
                </a>
                <button type="submit" class="import-btn submit">
                    <i class="fas fa-upload"></i>
                    آپلود و Import
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function downloadTemplate() {
    // ایجاد یک فایل CSV نمونه
    const headers = ['email', 'first_name', 'last_name', 'phone', 'national_id', 'gender', 'password', 'status', 'email_verified'];
    const sampleData = [
        ['user1@example.com', 'علی', 'احمدی', '09123456789', '1234567890', 'male', 'password123', 'active', '1'],
        ['user2@example.com', 'فاطمه', 'محمدی', '09123456790', '1234567891', 'female', 'password123', 'active', '0'],
    ];
    
    let csv = headers.join(',') + '\n';
    sampleData.forEach(row => {
        csv += row.join(',') + '\n';
    });
    
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'user_import_template.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
@endsection

