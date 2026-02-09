@extends('layouts.admin')

@section('title', ($stock ? 'ویرایش' : 'ثبت') . ' اطلاعات پایه سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', ($stock ? 'ویرایش' : 'ثبت') . ' اطلاعات پایه سهام')
@section('page-description', 'تنظیم و مدیریت اطلاعات پایه سهام استارتاپ')

@push('styles')
<style>
    .stock-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .stock-form-group {
        margin-bottom: 1.5rem;
    }
    
    .stock-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .stock-form-label .required {
        color: #ef4444;
    }
    
    .stock-form-input, .stock-form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .stock-form-input:focus, .stock-form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .stock-form-textarea {
        min-height: 150px;
        resize: vertical;
    }
    
    .stock-form-help {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
    
    .stock-form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }
    
    .stock-form-btn {
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
    
    .stock-form-btn.submit {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .stock-form-btn.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .stock-form-btn.cancel {
        background: #6b7280;
        color: white;
    }
    
    .stock-form-btn.cancel:hover {
        background: #4b5563;
    }
    
    .stock-info-box {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stock-info-box-title {
        font-weight: 600;
        color: #0369a1;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .stock-info-box-content {
        font-size: 0.875rem;
        color: #0c4a6e;
        line-height: 1.6;
    }
    
    @media (prefers-color-scheme: dark) {
        .stock-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .stock-form-label {
            color: #f1f5f9 !important;
        }
        
        .stock-form-input, .stock-form-textarea {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .stock-form-help {
            color: #94a3b8 !important;
        }
        
        .stock-info-box {
            background: #1e3a8a !important;
            border-color: #3b82f6 !important;
        }
        
        .stock-info-box-title {
            color: #bfdbfe !important;
        }
        
        .stock-info-box-content {
            color: #bfdbfe !important;
        }
    }
    
    @media (max-width: 768px) {
        .stock-form-card {
            padding: 1rem;
        }
        
        .stock-form-actions {
            flex-direction: column;
        }
        
        .stock-form-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="stock-form-card">
        <form action="{{ route('admin.stock.store') }}" method="POST">
            @csrf
            
            <div class="stock-info-box">
                <div class="stock-info-box-title">
                    <i class="fas fa-info-circle ml-2"></i>
                    نکته مهم
                </div>
                <div class="stock-info-box-content">
                    مقادیر مالی در فرم و دیتابیس به صورت <strong>ریال</strong> هستند.
                </div>
            </div>
            
            <div class="stock-form-group">
                <label for="startup_valuation" class="stock-form-label">
                    ارزش پایه استارتاپ (ریال) <span class="required">*</span>
                </label>
                <input type="number" 
                       id="startup_valuation" 
                       name="startup_valuation" 
                       class="stock-form-input @error('startup_valuation') border-red-500 @enderror" 
                       value="{{ old('startup_valuation', isset($stock) ? $stock->startup_valuation * 10 : '') }}" 
                       required
                       min="0"
                       step="1"
                       placeholder="مثال: 10000000000">
                <div class="stock-form-help">ارزش کل استارتاپ به ریال</div>
                @error('startup_valuation')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="stock-form-group">
                <label for="total_shares" class="stock-form-label">
                    تعداد کل سهام <span class="required">*</span>
                </label>
                <input type="number" 
                       id="total_shares" 
                       name="total_shares" 
                       class="stock-form-input @error('total_shares') border-red-500 @enderror" 
                       value="{{ old('total_shares', $stock->total_shares ?? '') }}" 
                       required
                       min="1"
                       step="1"
                       placeholder="مثال: 1000000">
                <div class="stock-form-help">تعداد کل سهام منتشر شده</div>
                @error('total_shares')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="stock-form-group">
                <label for="available_shares" class="stock-form-label">
                    تعداد سهام قابل عرضه
                </label>
                <input type="number" 
                       id="available_shares" 
                       name="available_shares" 
                       class="stock-form-input @error('available_shares') border-red-500 @enderror" 
                       value="{{ old('available_shares', $stock->available_shares ?? '') }}" 
                       min="0"
                       step="1"
                       placeholder="مثال: 500000">
                <div class="stock-form-help">تعداد سهامی که برای عرضه در حراج‌ها در دسترس است</div>
                @error('available_shares')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="stock-form-group">
                <label for="base_share_price" class="stock-form-label">
                    ارزش پایه هر سهم (ریال) <span class="required">*</span>
                </label>
                <input type="number" 
                       id="base_share_price" 
                       name="base_share_price" 
                       class="stock-form-input @error('base_share_price') border-red-500 @enderror" 
                       value="{{ old('base_share_price', isset($stock) ? $stock->base_share_price * 10 : '') }}" 
                       required
                       min="0"
                       step="0.01"
                       placeholder="مثال: 100000">
                <div class="stock-form-help">قیمت پایه هر سهم به ریال</div>
                @error('base_share_price')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="stock-form-group">
                <label for="info" class="stock-form-label">
                    توضیحات تکمیلی
                </label>
                <textarea id="info" 
                          name="info" 
                          class="stock-form-textarea @error('info') border-red-500 @enderror" 
                          placeholder="توضیحات اضافی درباره سهام...">{{ old('info', $stock->info ?? '') }}</textarea>
                @error('info')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="stock-form-actions">
                <a href="{{ route('admin.stock.index') }}" class="stock-form-btn cancel">
                    <i class="fas fa-times"></i>
                    انصراف
                </a>
                <button type="submit" class="stock-form-btn submit">
                    <i class="fas fa-save"></i>
                    ذخیره اطلاعات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
