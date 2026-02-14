@extends('layouts.unified')

@section('title', 'حساب‌های فرعی نجم بهار - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .sub-accounts-container {
        direction: rtl;
    }
    
    .account-card {
        background: var(--nb-color-white);
        border-radius: var(--nb-radius-lg);
        padding: var(--nb-space-6);
        box-shadow: var(--nb-shadow-md);
        border: 1px solid var(--nb-color-neutral-200);
        margin-bottom: var(--nb-space-6);
    }
    
    .sub-account-card {
        background: var(--nb-color-white);
        border-radius: var(--nb-radius-lg);
        padding: var(--nb-space-6);
        box-shadow: var(--nb-shadow-md);
        border: 1px solid var(--nb-color-neutral-200);
        transition: transform var(--nb-duration-base), box-shadow var(--nb-duration-base);
        will-change: transform, box-shadow;
    }
    
    .sub-account-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--nb-shadow-lg);
    }
    
    .balance-display {
        font-size: var(--nb-font-size-3xl);
        font-weight: var(--nb-font-weight-black);
        color: var(--nb-color-earth-green);
        margin: var(--nb-space-4) 0;
    }
    
    .sub-account-code {
        font-family: monospace;
        font-size: var(--nb-font-size-lg);
        color: var(--nb-color-ocean-blue);
        font-weight: var(--nb-font-weight-semibold);
    }
</style>
@endpush

@php
$routePrefix = $routePrefix ?? 'najm-bahar';
$routeParams = $routeParams ?? [];
$accountLabel = $accountLabel ?? 'حساب اصلی';
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class="nb-page-container" style="max-width: var(--nb-container-max-width-xl);">
        <div class="sub-accounts-container">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gentle-black mb-2">
                        <i class="fas fa-wallet ml-3" style="color: var(--color-earth-green);" aria-hidden="true"></i>
                        حساب‌های فرعی
                    </h1>
                    <p class="text-slate-600 dark:text-slate-400">مدیریت حساب‌های فرعی خود</p>
                </div>
                <a href="{{ route($routePrefix . '.sub-accounts.create', $routeParams) }}" 
                   class="nb-btn nb-btn-primary">
                    <i class="fas fa-plus ml-2" aria-hidden="true"></i>
                    ایجاد حساب فرعی جدید
                </a>
            </div>

            @if(session('success'))
                <div class="bg-green-50 border-r-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert" aria-live="polite">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle ml-3" aria-hidden="true"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border-r-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert" aria-live="assertive">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle ml-3" aria-hidden="true"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                </div>
            @endif

            <!-- حساب اصلی -->
            <div class="account-card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gentle-black">
                        <i class="fas fa-credit-card ml-2" style="color: var(--color-earth-green);"></i>
                        {{ $accountLabel }}
                    </h2>
                    @if($routePrefix === 'najm-bahar')
                        <a href="{{ route('najm-bahar.dashboard') }}" 
                           class="text-sm text-ocean-blue hover:underline">
                            مشاهده داشبورد
                        </a>
                    @elseif(isset($routeParams['group']))
                        <a href="{{ route('groups.show', $routeParams['group']) }}" 
                           class="text-sm text-ocean-blue hover:underline">
                            مشاهده گروه
                        </a>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">شماره حساب</p>
                        <p class="sub-account-code">{{ $account->account_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-600 mb-1">موجودی</p>
                        <p class="balance-display">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</p>
                    </div>
                </div>
            </div>

            <!-- لیست حساب‌های فرعی -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gentle-black mb-4">
                    <i class="fas fa-list ml-2" style="color: var(--color-ocean-blue);"></i>
                    حساب‌های فرعی ({{ $subAccounts->count() }})
                </h2>
            </div>

            @if($subAccounts->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($subAccounts as $subAccount)
                        <div class="sub-account-card">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-bold text-gentle-black">
                                        {{ $subAccount->name }}
                                    </h3>
                                    @if((int) ($subAccount->status ?? 1) !== 1)
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">غیرفعال</span>
                                    @endif
                                </div>
                                <a href="{{ route($routePrefix . '.sub-accounts.show', array_merge($routeParams, ['subAccount' => $subAccount])) }}" 
                                   class="text-ocean-blue hover:text-dark-blue">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                            
                            <div class="mb-3">
                                <p class="text-sm text-slate-600 mb-1">شماره حساب</p>
                                <p class="sub-account-code">{{ str_replace('-', '/', $subAccount->sub_account_code) }}</p>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-sm text-slate-600 mb-1">موجودی</p>
                                <p class="text-2xl font-bold" style="color: var(--color-earth-green);">
                                    {{ \App\Helpers\BaharMoney::formatDecimalHtml($subAccount->balance) }}
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                          <a href="{{ route($routePrefix . '.sub-accounts.show', array_merge($routeParams, ['subAccount' => $subAccount])) }}" 
                                   class="flex-1 text-center px-3 py-2 bg-ocean-blue text-white rounded-lg hover:bg-opacity-90 transition-colors text-sm">
                                    <i class="fas fa-eye ml-1"></i>
                                    مشاهده
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="account-card text-center py-12">
                    <i class="fas fa-inbox text-5xl text-slate-400 mb-4"></i>
                    <p class="text-lg text-slate-600 mb-2">هنوز حساب فرعی ایجاد نکرده‌اید</p>
                    <p class="text-sm text-slate-500 mb-4">حساب‌های فرعی به شما امکان مدیریت بهتر وجوه را می‌دهند</p>
                          <a href="{{ route($routePrefix . '.sub-accounts.create', $routeParams) }}" 
                       class="inline-block px-6 py-3 bg-earth-green text-white rounded-lg hover:bg-opacity-90 transition-colors">
                        <i class="fas fa-plus ml-2"></i>
                        ایجاد اولین حساب فرعی
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection


