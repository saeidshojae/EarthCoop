@extends('layouts.unified')

@section('title', 'ایجاد حساب فرعی - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .form-container {
        direction: rtl;
    }
</style>
@endpush

@php
$routePrefix = $routePrefix ?? 'najm-bahar';
$routeParams = $routeParams ?? [];
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-4 md:px-6 max-w-2xl">
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
                <div class="bg-red-50 border-r-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- فرم ایجاد -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <form action="{{ route($routePrefix . '.sub-accounts.store', $routeParams) }}" method="POST">
                    @csrf
                    
                    <div class="mb-6">
                        <label for="name" class="block text-sm font-semibold text-slate-700 mb-2">
                            نام حساب فرعی (اختیاری)
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent"
                               placeholder="مثال: حساب پس‌انداز">
                        <p class="mt-1 text-xs text-slate-500">
                            اگر نامی وارد نکنید، نام پیش‌فرض به صورت خودکار ایجاد می‌شود
                        </p>
                    </div>

                    <div class="bg-blue-50 border-r-4 border-blue-500 p-4 rounded-lg mb-6">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle ml-2"></i>
                            حساب فرعی با شماره حساب منحصر به فرد ایجاد می‌شود و می‌توانید وجوه را بین حساب اصلی و حساب‌های فرعی منتقل کنید.
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                                <a href="{{ route($routePrefix . '.sub-accounts.index', $routeParams) }}" 
                           class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                            <i class="fas fa-arrow-right ml-2"></i>
                            انصراف
                        </a>
                        <button type="submit" 
                                class="px-6 py-3 bg-earth-green text-white rounded-lg hover:bg-opacity-90 transition-colors font-medium">
                            <i class="fas fa-save ml-2"></i>
                            ایجاد حساب فرعی
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection


