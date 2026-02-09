@extends('layouts.admin')

@section('title', 'ایجاد دسته‌بندی جدید - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ایجاد دسته‌بندی جدید')
@section('page-description', 'ایجاد دسته‌بندی جدید برای وبلاگ')

@push('styles')
<link rel="stylesheet" href="{{ asset('Css/admin-styles.css') }}">
<style>
    .blog-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .blog-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-form-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .blog-form-group {
        margin-bottom: 1.5rem;
    }
    
    .blog-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .blog-form-label .required {
        color: #ef4444;
    }
    
    .blog-form-input, .blog-form-select, .blog-form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .blog-form-input:focus, .blog-form-select:focus, .blog-form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .blog-form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .blog-form-help {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .blog-form-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .blog-form-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .blog-form-checkbox label {
        font-size: 0.875rem;
        color: #1e293b;
        cursor: pointer;
    }
    
    .blog-form-submit {
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        justify-content: center;
    }
    
    .blog-form-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .blog-form-back {
        padding: 0.75rem 1.5rem;
        background: #f3f4f6;
        color: #374151;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .blog-form-back:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    
    .blog-error-alert {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 2rem;
    }
    
    .blog-error-alert ul {
        margin: 0;
        padding-right: 1.5rem;
    }
    
    .blog-error-alert li {
        margin-bottom: 0.5rem;
    }
    
    @media (prefers-color-scheme: dark) {
        .blog-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-form-header h3 {
            color: #f1f5f9 !important;
        }
        
        .blog-form-label {
            color: #f1f5f9 !important;
        }
        
        .blog-form-input, .blog-form-select, .blog-form-textarea {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-form-input:focus, .blog-form-select:focus, .blog-form-textarea:focus {
            border-color: #3b82f6 !important;
        }
        
        .blog-form-help {
            color: #94a3b8 !important;
        }
        
        .blog-form-checkbox label {
            color: #f1f5f9 !important;
        }
        
        .blog-form-back {
            background: #334155 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-form-back:hover {
            background: #475569 !important;
        }
        
        .blog-error-alert {
            background: #450a0a !important;
            border-color: #b91c1c !important;
            color: #fecaca !important;
        }
    }
    
    @media (max-width: 768px) {
        .blog-form-card {
            padding: 1rem;
        }
        
        .blog-form-header {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="blog-form-card">
        <div class="blog-form-header">
            <h3>
                <i class="fas fa-plus-circle ml-2"></i>
                ایجاد دسته‌بندی جدید
            </h3>
            <a href="{{ route('admin.blog.categories') }}" class="blog-form-back">
                <i class="fas fa-arrow-right"></i>
                بازگشت
            </a>
        </div>
    </div>
    
    @if($errors->any())
    <div class="blog-form-card">
        <div class="blog-error-alert">
            <strong>خطاهای زیر رخ داد:</strong>
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif
    
    <form action="{{ route('admin.blog.categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="blog-form-card">
            <div class="blog-form-group">
                <label for="name" class="blog-form-label">
                    نام دسته‌بندی <span class="required">*</span>
                </label>
                <input type="text" 
                       class="blog-form-input" 
                       id="name" 
                       name="name" 
                       value="{{ old('name') }}" 
                       required
                       placeholder="نام دسته‌بندی را وارد کنید">
            </div>
            
            <div class="blog-form-group">
                <label for="slug" class="blog-form-label">نامک (Slug)</label>
                <input type="text" 
                       class="blog-form-input" 
                       id="slug" 
                       name="slug" 
                       value="{{ old('slug') }}" 
                       placeholder="به صورت خودکار از نام تولید می‌شود">
                <div class="blog-form-help">اگر خالی بگذارید، به صورت خودکار ساخته می‌شود</div>
            </div>
            
            <div class="blog-form-group">
                <label for="description" class="blog-form-label">توضیحات</label>
                <textarea class="blog-form-textarea" 
                          id="description" 
                          name="description" 
                          rows="4"
                          placeholder="توضیحات دسته‌بندی را وارد کنید">{{ old('description') }}</textarea>
            </div>
            
            <div class="blog-form-group">
                <label for="image" class="blog-form-label">تصویر دسته‌بندی</label>
                <input type="file" 
                       class="blog-form-input" 
                       id="image" 
                       name="image" 
                       accept="image/*">
                <div class="blog-form-help">حداکثر 5 مگابایت (jpg, png, gif)</div>
            </div>
            
            <div class="blog-form-group">
                <label for="order" class="blog-form-label">ترتیب نمایش</label>
                <input type="number" 
                       class="blog-form-input" 
                       id="order" 
                       name="order" 
                       value="{{ old('order', 0) }}"
                       placeholder="0">
                <div class="blog-form-help">عدد کمتر = نمایش بالاتر</div>
            </div>
            
            <div class="blog-form-checkbox">
                <input type="checkbox" 
                       id="is_active" 
                       name="is_active" 
                       value="1" 
                       {{ old('is_active', true) ? 'checked' : '' }}>
                <label for="is_active">فعال</label>
            </div>
            
            <button type="submit" class="blog-form-submit">
                <i class="fas fa-save"></i>
                ذخیره دسته‌بندی
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Auto-generate slug from name
    document.getElementById('name').addEventListener('input', function(e) {
        if (!document.getElementById('slug').value) {
            const slug = e.target.value
                .toLowerCase()
                .replace(/[^\u0600-\u06FF\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        }
    });
</script>
@endpush

