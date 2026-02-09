@extends('layouts.admin')

@section('title', 'هدیه دادن سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'هدیه دادن سهام')
@section('page-description', 'هدیه دادن سهام به کاربران')

@push('styles')
<style>
    .gift-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .gift-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .gift-form-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .gift-form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }
    
    .gift-form-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .user-select-container {
        max-height: 300px;
        overflow-y: auto;
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1rem;
        background: #f9fafb;
    }
    
    .user-checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        background: white;
        transition: all 0.2s;
    }
    
    .user-checkbox-item:hover {
        background: #f3f4f6;
    }
    
    .user-checkbox-item input[type="checkbox"] {
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
    }
    
    .gift-submit-btn {
        padding: 0.875rem 2rem;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .gift-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .info-box {
        background: #eff6ff;
        border-right: 4px solid #3b82f6;
        border-radius: 0.75rem;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .info-box.warning {
        background: #fef3c7;
        border-right-color: #f59e0b;
    }
    
    @media (prefers-color-scheme: dark) {
        .gift-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .gift-form-header {
            border-bottom-color: #475569 !important;
        }
        
        .gift-form-header h3 {
            color: #f1f5f9 !important;
        }
        
        .gift-form-input {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .user-select-container {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .user-checkbox-item {
            background: #475569 !important;
        }
        
        .user-checkbox-item:hover {
            background: #4b5563 !important;
        }
        
        .info-box {
            background: #1e3a8a !important;
            color: #bfdbfe !important;
        }
        
        .info-box.warning {
            background: #78350f !important;
            color: #fed7aa !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="gift-form-card">
        <div class="gift-form-header">
            <h3>
                <i class="fas fa-gift ml-2"></i>
                هدیه دادن سهام
            </h3>
            <a href="{{ route('admin.stock.index') }}" style="padding: 0.75rem 1.5rem; background: #6b7280; color: white; border-radius: 0.75rem; text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-right ml-2"></i>
                بازگشت
            </a>
        </div>
        
        @if($stock)
        <div class="info-box warning">
            <strong><i class="fas fa-info-circle ml-2"></i> توجه:</strong>
            سهام از موجودی <strong>سهام قابل عرضه</strong> کسر می‌شود (نه از کل سهام).
            موجودی فعلی: <strong>{{ number_format($stock->available_shares) }} سهم</strong>
        </div>
        
        <form method="POST" action="{{ route('admin.stock.gift.store') }}" class="space-y-6">
            @csrf
            
            <div>
                <label class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">
                    انتخاب کاربر(ان)
                </label>
                <div class="user-select-container">
                    <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid #e5e7eb;">
                        <input type="checkbox" id="selectAllUsers" style="width: 1.25rem; height: 1.25rem; cursor: pointer;">
                        <label for="selectAllUsers" style="margin-right: 0.5rem; font-weight: 600; cursor: pointer;">انتخاب همه</label>
                    </div>
                    @foreach($users as $user)
                    <div class="user-checkbox-item">
                        <input type="checkbox" 
                               name="user_ids[]" 
                               value="{{ $user->id }}" 
                               id="user_{{ $user->id }}"
                               class="user-checkbox">
                        <label for="user_{{ $user->id }}" style="flex: 1; cursor: pointer;">
                            <strong>{{ ($user->first_name ?? '') . ' ' . ($user->last_name ?? '') }}</strong>
                            <span style="color: #6b7280; font-size: 0.875rem; margin-right: 0.5rem;">({{ $user->email }})</span>
                        </label>
                    </div>
                    @endforeach
                </div>
                @error('user_ids')
                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label for="quantity" class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">
                    تعداد سهام برای هر کاربر
                </label>
                <input type="number" 
                       id="quantity" 
                       name="quantity" 
                       min="1" 
                       class="gift-form-input @error('quantity') border-red-400 @enderror"
                       value="{{ old('quantity', '') }}"
                       required>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    این تعداد برای هر کاربر انتخاب شده اعمال می‌شود.
                </p>
                @error('quantity')
                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label for="description" class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">
                    توضیحات (اختیاری)
                </label>
                <textarea id="description" 
                          name="description" 
                          rows="3" 
                          class="gift-form-input @error('description') border-red-400 @enderror"
                          placeholder="مثلاً: هدیه به مناسبت عضویت در پلتفرم">{{ old('description', '') }}</textarea>
                @error('description')
                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div id="totalPreview" style="padding: 1rem; background: #f0fdf4; border-radius: 0.75rem; border-right: 4px solid #10b981; display: none;">
                <strong>جمع کل سهام:</strong> <span id="totalShares">0</span> سهم
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-start;">
                <button type="submit" class="gift-submit-btn">
                    <i class="fas fa-gift ml-2"></i>
                    هدیه دادن سهام
                </button>
            </div>
        </form>
        @else
        <div style="text-align: center; padding: 3rem 1rem;">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #f59e0b; margin-bottom: 1rem;"></i>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">اطلاعات سهام ثبت نشده است</div>
            <p style="color: #6b7280; font-size: 0.875rem;">ابتدا باید اطلاعات پایه سهام را ثبت کنید.</p>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAllUsers');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const quantityInput = document.getElementById('quantity');
        const totalPreview = document.getElementById('totalPreview');
        const totalSharesSpan = document.getElementById('totalShares');
        
        // انتخاب همه
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateTotalPreview();
            });
        }
        
        // به‌روزرسانی انتخاب همه
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = Array.from(userCheckboxes).every(cb => cb.checked);
                }
                updateTotalPreview();
            });
        });
        
        // به‌روزرسانی پیش‌نمایش
        function updateTotalPreview() {
            const selectedCount = Array.from(userCheckboxes).filter(cb => cb.checked).length;
            const quantity = parseInt(quantityInput.value) || 0;
            const total = selectedCount * quantity;
            
            if (selectedCount > 0 && quantity > 0) {
                totalSharesSpan.textContent = number_format(total);
                totalPreview.style.display = 'block';
            } else {
                totalPreview.style.display = 'none';
            }
        }
        
        quantityInput.addEventListener('input', updateTotalPreview);
        
        function number_format(number) {
            return new Intl.NumberFormat('fa-IR').format(number);
        }
    });
</script>
@endpush

