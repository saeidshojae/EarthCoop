@extends('layouts.unified')

@section('title', 'ایجاد حساب فرعی - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .form-container {
        direction: rtl;
    }
    
    .create-card {
        background: var(--nb-color-white);
        border-radius: var(--nb-radius-lg);
        padding: var(--nb-space-6);
        box-shadow: var(--nb-shadow-md);
        border: 1px solid var(--nb-color-neutral-200);
    }
</style>
@endpush

@php
$routePrefix = $routePrefix ?? 'najm-bahar';
$routeParams = $routeParams ?? [];
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class="nb-page-container" style="max-width: var(--nb-container-max-width-sm);">
        <div class="form-container">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gentle-black mb-2">
                    <i class="fas fa-plus-circle ml-3" style="color: var(--color-earth-green);"></i>
                    ایجاد حساب فرعی جدید
                </h1>
                <p class="text-slate-600 dark:text-slate-400">ایجاد حساب فرعی برای مدیریت بهتر وجوه</p>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border-r-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert" aria-live="assertive">
                    <p class="font-semibold mb-2">خطاهای ارسال:</p>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- فرم ایجاد -->
            <div class="create-card">
                <form action="{{ route($routePrefix . '.sub-accounts.store', $routeParams) }}" method="POST" id="createForm">
                    @csrf
                    
                    <div class="mb-6">
                        <label for="name" class="nb-label">
                            نام حساب فرعی (اختیاری)
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}"
                               class="nb-input"
                               placeholder="مثال: حساب پس‌انداز"
                               aria-describedby="name-help">
                        <span id="name-help" class="nb-help-text">
                            اگر نامی وارد نکنید، نام پیش‌فرض به صورت خودکار ایجاد می‌شود
                        </span>
                    </div>

                    <div class="bg-blue-50 border-r-4 border-blue-500 p-4 rounded-lg mb-6" role="note">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle ml-2" aria-hidden="true"></i>
                            حساب فرعی با شماره حساب منحصر به فرد ایجاد می‌شود و می‌توانید وجوه را بین حساب اصلی و حساب‌های فرعی منتقل کنید.
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <a href="{{ route($routePrefix . '.sub-accounts.index', $routeParams) }}" 
                           class="w-full sm:w-auto px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium text-center">
                            <i class="fas fa-arrow-right ml-2" aria-hidden="true"></i>
                            انصراف
                        </a>
                        <button type="submit" 
                                class="w-full sm:w-auto px-6 py-3 nb-btn nb-btn-primary">
                            <i class="fas fa-save ml-2" aria-hidden="true"></i>
                            ایجاد حساب فرعی
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection


