@extends('layouts.unified')

@section('title', 'جزئیات حساب فرعی - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .sub-account-detail-container {
        direction: rtl;
    }
    
    .detail-card {
        background: var(--nb-color-white);
        border-radius: var(--nb-radius-lg);
        padding: var(--nb-space-8);
        box-shadow: var(--nb-shadow-md);
        border: 1px solid var(--nb-color-neutral-200);
        margin-bottom: var(--nb-space-6);
    }
    
    .balance-large {
        font-size: var(--nb-font-size-5xl);
        font-weight: var(--nb-font-weight-black);
        color: var(--nb-color-earth-green);
        margin: var(--nb-space-4) 0;
    }
</style>
@endpush

@php
$routePrefix = $routePrefix ?? 'najm-bahar';
$routeParams = $routeParams ?? [];
$isInactive = (int) ($subAccount->status ?? 1) !== 1;
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class="nb-page-container" style="max-width: var(--nb-container-max-width-md);">
        <div class="sub-account-detail-container">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gentle-black mb-2">
                        <i class="fas fa-wallet ml-3" style="color: var(--color-earth-green);" aria-hidden="true"></i>
                        {{ $subAccount->name }}
                    </h1>
                    <p class="text-slate-600 dark:text-slate-400">جزئیات حساب فرعی</p>
                </div>
                <a href="{{ route($routePrefix . '.sub-accounts.index', $routeParams) }}" 
                   class="w-full sm:w-auto px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-center">
                    <i class="fas fa-arrow-right ml-2" aria-hidden="true"></i>
                    بازگشت
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

            <!-- اطلاعات حساب -->
            <div class="detail-card">
                <h2 class="text-xl font-bold text-gentle-black mb-4">
                    <i class="fas fa-info-circle ml-2" style="color: var(--color-ocean-blue);"></i>
                    اطلاعات حساب
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">شماره حساب</p>
                        <p class="text-lg font-mono font-bold text-ocean-blue">{{ str_replace('-', '/', $subAccount->sub_account_code) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-600 mb-1">نام حساب</p>
                        <p class="text-lg font-bold text-gentle-black">{{ $subAccount->name }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-slate-600 mb-1">موجودی</p>
                        <p class="balance-large">{{ \App\Helpers\BaharMoney::formatDecimalHtml($subAccount->balance) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-600 mb-1">تاریخ ایجاد</p>
                        <p class="text-base text-gentle-black">{{ \Morilog\Jalali\Jalalian::fromCarbon($subAccount->created_at)->format('Y/m/d H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-600 mb-1">وضعیت</p>
                        @if($isInactive)
                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                                غیرفعال
                            </span>
                        @else
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                فعال
                            </span>
                        @endif
                    </div>
                </div>
                <form action="{{ route($routePrefix . '.sub-accounts.update', array_merge($routeParams, ['subAccount' => $subAccount])) }}" method="POST" class="mt-6 border-t border-slate-200 pt-5">
                        @csrf
                        @method('PUT')
                        <label for="sub_account_name" class="nb-label">ویرایش نام حساب فرعی</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <input type="text" id="sub_account_name" name="name" value="{{ old('name', $subAccount->name) }}" class="flex-1 nb-input" required>
                            <button type="submit" class="w-full sm:w-auto px-6 py-2 nb-btn nb-btn-primary">
                                <i class="fas fa-pen ml-2" aria-hidden="true"></i>
                                بروزرسانی نام
                            </button>
                        </div>
                    </form>
            </div>

            <!-- انتقال وجه -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- انتقال به حساب فرعی -->
                <div class="detail-card">
                    <h3 class="text-lg font-bold text-gentle-black mb-4">
                        <i class="fas fa-arrow-down ml-2" style="color: var(--color-earth-green);"></i>
                        انتقال به حساب فرعی
                    </h3>
                    <p class="text-sm text-slate-600 mb-4">
                        موجودی حساب اصلی: <strong>{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</strong>
                    </p>
                    @if($isInactive)
                        <p class="text-sm text-red-600 mb-4">این حساب غیرفعال است و امکان انتقال وجود ندارد.</p>
                    @endif
                    <form action="{{ route($routePrefix . '.sub-accounts.transfer-to', array_merge($routeParams, ['subAccount' => $subAccount])) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="amount_to" class="nb-label">
                                مبلغ (بهار.گل)
                            </label>
                            <input type="text" 
                                   id="amount_to" 
                                   name="amount" 
                                   inputmode="decimal"
                                   required
                                   class="nb-input" 
                                   placeholder="مثال: 10.30"
                                   {{ $isInactive ? 'disabled' : '' }}>
                        </div>
                        <div class="mb-4">
                            <label for="description_to" class="nb-label">
                                توضیحات (اختیاری)
                            </label>
                            <textarea id="description_to" 
                                      name="description" 
                                      rows="2"
                                      class="nb-input"
                                      placeholder="توضیحات تراکنش" {{ $isInactive ? 'disabled' : '' }}></textarea>
                        </div>
                        <button type="submit" 
                                class="w-full nb-btn nb-btn-primary" {{ $isInactive ? 'disabled' : '' }}>
                            <i class="fas fa-arrow-down ml-2" aria-hidden="true"></i>
                            انتقال به حساب فرعی
                        </button>
                    </form>
                </div>

                <!-- انتقال از حساب فرعی -->
                <div class="detail-card">
                    <h3 class="text-lg font-bold text-gentle-black mb-4">
                        <i class="fas fa-arrow-up ml-2" style="color: var(--color-red-tomato);"></i>
                        انتقال از حساب فرعی
                    </h3>
                    <p class="text-sm text-slate-600 mb-4">
                        موجودی حساب فرعی: <strong>{{ \App\Helpers\BaharMoney::formatDecimalHtml($subAccount->balance) }}</strong>
                    </p>
                    @if($isInactive)
                        <p class="text-sm text-red-600 mb-4">این حساب غیرفعال است و امکان انتقال وجود ندارد.</p>
                    @endif
                    <form action="{{ route($routePrefix . '.sub-accounts.transfer-from', array_merge($routeParams, ['subAccount' => $subAccount])) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="amount_from" class="nb-label">
                                مبلغ (بهار.گل)
                            </label>
                            <input type="text" 
                                   id="amount_from" 
                                   name="amount" 
                                   inputmode="decimal"
                                   required
                                   class="nb-input"
                                   placeholder="مثال: 10.30"
                                   {{ $isInactive ? 'disabled' : '' }}>
                        </div>
                        <div class="mb-4">
                            <label for="description_from" class="nb-label">
                                توضیحات (اختیاری)
                            </label>
                            <textarea id="description_from" 
                                      name="description" 
                                      rows="2"
                                      class="nb-input"
                                      placeholder="توضیحات تراکنش" {{ $isInactive ? 'disabled' : '' }}></textarea>
                        </div>
                        <button type="submit" 
                                class="w-full nb-btn nb-btn-primary" {{ $isInactive ? 'disabled' : '' }}>
                            <i class="fas fa-arrow-up ml-2" aria-hidden="true"></i>
                            انتقال از حساب فرعی
                        </button>
                    </form>
                    <div class="border-t border-slate-200 mt-6 pt-5">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3">بستن حساب فرعی و انتقال موجودی</h4>
                        <form action="{{ route($routePrefix . '.sub-accounts.close', array_merge($routeParams, ['subAccount' => $subAccount])) }}" method="POST" class="space-y-3" onsubmit="return confirm('آیا از بستن این حساب فرعی مطمئن هستید؟');">
                                @csrf
                                <div>
                                    <label class="nb-label">انتقال به</label>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" name="destination" value="main" checked>
                                            حساب اصلی
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" name="destination" value="subaccount">
                                            حساب فرعی دیگر
                                        </label>
                                    </div>
                                </div>
                                <div id="destination_subaccount_field" class="hidden">
                                    <label for="destination_sub_account_id" class="nb-label">انتخاب حساب فرعی مقصد</label>
                                    <select id="destination_sub_account_id" name="destination_sub_account_id" class="nb-input">
                                        <option value="">انتخاب حساب فرعی</option>
                                        @foreach($otherSubAccounts as $otherSubAccount)
                                            <option value="{{ $otherSubAccount->id }}">
                                                {{ $otherSubAccount->name }} - {{ str_replace('-', '/', $otherSubAccount->sub_account_code) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="close_description" class="nb-label">توضیحات (اختیاری)</label>
                                    <textarea id="close_description" name="description" rows="2" class="nb-input" placeholder="توضیحات بستن حساب"></textarea>
                                </div>
                                <button type="submit" class="w-full nb-btn nb-btn-danger">
                                    <i class="fas fa-lock ml-2" aria-hidden="true"></i>
                                    بستن حساب فرعی
                                </button>
                            </form>
                    </div>
                </div>
            </div>

            <!-- عملیات -->
            <div class="detail-card">
                <h3 class="text-lg font-bold text-gentle-black mb-4">
                    <i class="fas fa-cog ml-2" style="color: var(--color-digital-gold);"></i>
                    عملیات
                </h3>
                @if($isInactive)
                    <form action="{{ route($routePrefix . '.sub-accounts.activate', array_merge($routeParams, ['subAccount' => $subAccount])) }}" method="POST" onsubmit="return confirm('آیا از فعال سازی این حساب فرعی اطمینان دارید؟');">
                        @csrf
                        <button type="submit" class="px-6 py-2 nb-btn nb-btn-primary">
                            <i class="fas fa-check ml-2" aria-hidden="true"></i>
                            فعال سازی حساب فرعی
                        </button>
                    </form>
                @else
                    <form action="{{ route($routePrefix . '.sub-accounts.deactivate', array_merge($routeParams, ['subAccount' => $subAccount])) }}" method="POST" onsubmit="return confirm('آیا از غیرفعال کردن این حساب فرعی اطمینان دارید؟');">
                        @csrf
                        <button type="submit" class="px-6 py-2 nb-btn nb-btn-outline">
                            <i class="fas fa-ban ml-2" aria-hidden="true"></i>
                            غیرفعال کردن حساب فرعی
                        </button>
                        <p class="mt-2 text-sm text-slate-500">
                            حساب غیرفعال در لیست باقی می ماند و امکان فعال سازی مجدد دارد.
                        </p>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var destinationField = document.getElementById('destination_subaccount_field');
        var destinationSelect = document.getElementById('destination_sub_account_id');
        var destinationInputs = document.querySelectorAll('input[name="destination"]');

        if (!destinationField || !destinationInputs.length) {
            return;
        }

        var toggleDestinationField = function () {
            var selected = document.querySelector('input[name="destination"]:checked');
            var isSubAccount = selected && selected.value === 'subaccount';
            destinationField.classList.toggle('hidden', !isSubAccount);
            if (destinationSelect) {
                if (isSubAccount) {
                    destinationSelect.setAttribute('required', 'required');
                } else {
                    destinationSelect.removeAttribute('required');
                    destinationSelect.value = '';
                }
            }
        };

        destinationInputs.forEach(function (input) {
            input.addEventListener('change', toggleDestinationField);
        });

        toggleDestinationField();
    });
</script>
@endpush


