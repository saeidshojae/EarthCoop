@extends('layouts.admin')

@section('title', 'ویرایش کارمزد')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-edit ml-2"></i>
                ویرایش کارمزد
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ویرایش کارمزد «{{ $fee->name }}»</p>
        </div>
        <a href="{{ route('admin.najm-bahar.fees.index') }}" 
           class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فرم ویرایش کارمزد -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <form action="{{ route('admin.najm-bahar.fees.update', $fee) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- نام کارمزد -->
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        نام کارمزد <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name', $fee->name) }}"
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="مثال: کارمزد انتقال وجه">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- نوع کارمزد -->
                <div>
                    <label for="type" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        نوع کارمزد <span class="text-red-500">*</span>
                    </label>
                    <select id="type" 
                            name="type"
                            required
                            onchange="updateFeeFields()"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="fixed" {{ old('type', $fee->type) == 'fixed' ? 'selected' : '' }}>ثابت</option>
                        <option value="percentage" {{ old('type', $fee->type) == 'percentage' ? 'selected' : '' }}>درصدی</option>
                        <option value="combined" {{ old('type', $fee->type) == 'combined' ? 'selected' : '' }}>ترکیبی (ثابت + درصدی)</option>
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- نوع تراکنش -->
                <div>
                    <label for="transaction_type" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        نوع تراکنش <span class="text-red-500">*</span>
                    </label>
                    <select id="transaction_type" 
                            name="transaction_type"
                            required
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="all" {{ old('transaction_type', $fee->transaction_type) == 'all' ? 'selected' : '' }}>همه تراکنش‌ها</option>
                        <option value="immediate" {{ old('transaction_type', $fee->transaction_type) == 'immediate' ? 'selected' : '' }}>فوری</option>
                        <option value="scheduled" {{ old('transaction_type', $fee->transaction_type) == 'scheduled' ? 'selected' : '' }}>زمان‌بندی شده</option>
                        <option value="fee" {{ old('transaction_type', $fee->transaction_type) == 'fee' ? 'selected' : '' }}>کارمزد</option>
                        <option value="adjustment" {{ old('transaction_type', $fee->transaction_type) == 'adjustment' ? 'selected' : '' }}>تعدیل</option>
                    </select>
                    @error('transaction_type')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- مبلغ ثابت -->
                <div id="fixed_amount_field">
                    <label for="fixed_amount" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                مبلغ ثابت (بهار.گل)
                    </label>
                    <input type="number" 
                           id="fixed_amount" 
                           name="fixed_amount" 
                                    value="{{ old('fixed_amount', \App\Helpers\BaharMoney::formatDecimalValue($fee->fixed_amount)) }}"
                           min="0"
                                    step="0.01"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="0">
                    @error('fixed_amount')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- درصد -->
                <div id="percentage_field">
                    <label for="percentage" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        درصد (%)
                    </label>
                    <input type="number" 
                           id="percentage" 
                           name="percentage" 
                           value="{{ old('percentage', $fee->percentage) }}"
                           min="0"
                           max="100"
                           step="0.01"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="0.00">
                    @error('percentage')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- حداقل مبلغ -->
                <div>
                    <label for="min_amount" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                حداقل مبلغ (بهار.گل) - اختیاری
                    </label>
                    <input type="number" 
                           id="min_amount" 
                           name="min_amount" 
                                    value="{{ old('min_amount', $fee->min_amount !== null ? \App\Helpers\BaharMoney::formatDecimalValue($fee->min_amount) : '') }}"
                           min="0"
                                    step="0.01"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="خالی = بدون محدودیت">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        کارمزد فقط برای تراکنش‌های بالاتر از این مبلغ اعمال می‌شود
                    </p>
                    @error('min_amount')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- حداکثر مبلغ -->
                <div>
                    <label for="max_amount" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                حداکثر مبلغ (بهار.گل) - اختیاری
                    </label>
                    <input type="number" 
                           id="max_amount" 
                           name="max_amount" 
                                    value="{{ old('max_amount', $fee->max_amount !== null ? \App\Helpers\BaharMoney::formatDecimalValue($fee->max_amount) : '') }}"
                           min="0"
                                    step="0.01"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="خالی = بدون محدودیت">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        کارمزد فقط برای تراکنش‌های پایین‌تر از این مبلغ اعمال می‌شود
                    </p>
                    @error('max_amount')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- توضیحات -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        توضیحات
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3"
                              class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                              placeholder="توضیحات اختیاری درباره این کارمزد">{{ old('description', $fee->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- وضعیت -->
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1"
                               {{ old('is_active', $fee->is_active) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                        <span class="mr-2 text-sm font-semibold text-slate-700 dark:text-slate-300">کارمزد فعال است</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 space-x-reverse pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('admin.najm-bahar.fees.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    انصراف
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function updateFeeFields() {
    const type = document.getElementById('type').value;
    const fixedField = document.getElementById('fixed_amount_field');
    const percentageField = document.getElementById('percentage_field');
    
    if (type === 'fixed') {
        fixedField.style.display = 'block';
        percentageField.style.display = 'none';
        document.getElementById('fixed_amount').required = true;
        document.getElementById('percentage').required = false;
    } else if (type === 'percentage') {
        fixedField.style.display = 'none';
        percentageField.style.display = 'block';
        document.getElementById('fixed_amount').required = false;
        document.getElementById('percentage').required = true;
    } else { // combined
        fixedField.style.display = 'block';
        percentageField.style.display = 'block';
        document.getElementById('fixed_amount').required = true;
        document.getElementById('percentage').required = true;
    }
}

// اجرای اولیه
document.addEventListener('DOMContentLoaded', function() {
    updateFeeFields();
});
</script>
@endpush
@endsection

