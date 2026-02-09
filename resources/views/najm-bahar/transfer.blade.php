@extends('layouts.unified')

@section('title', 'انتقال وجه نجم بهار')

@push('styles')
<style>
    .nb-transfer-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
    }

    .nb-transfer-header {
        background: linear-gradient(135deg, #0f766e 0%, #10b981 55%, #60a5fa 100%);
        color: #ffffff;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 14px 32px rgba(15, 118, 110, 0.2);
    }

    .nb-transfer-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.18);
    }
</style>
@endpush

@php
    $routePrefix = $routePrefix ?? 'najm-bahar';
    $routeParams = $routeParams ?? [];
    $isGroupTransfer = $routePrefix !== 'najm-bahar';
    $transferOwnerLabel = $transferOwnerLabel ?? null;
    $requireDescription = $requireDescription ?? false;
    $fallbackUrl = $isGroupTransfer
        ? route('groups.najm-bahar.dashboard', $routeParams)
        : route('najm-bahar.wallet');
@endphp

@section('content')
<div class="bg-light-gray/60 py-10 md:py-12" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-4xl space-y-6">
        @php
            $backUrl = url()->previous();
            $fallbackUrl = $fallbackUrl;
            if (! $backUrl || $backUrl === url()->current()) {
                $backUrl = $fallbackUrl;
            }
        @endphp
        <section class="nb-transfer-header">
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="nb-transfer-chip">
                        <i class="fas fa-exchange-alt"></i>
                        انتقال وجه نجم بهار
                    </div>
                    <a href="{{ $backUrl }}" class="px-4 py-2 bg-white/15 text-white rounded-full hover:bg-white/25 transition">
                        <i class="fas fa-arrow-right ml-2"></i>
                        بازگشت
                    </a>
                </div>
                <h1 class="text-3xl md:text-4xl font-black">انتقال بین حساب‌های فرعی{{ $transferOwnerLabel ? ' ' . $transferOwnerLabel : '' }}</h1>
                <p class="text-sm md:text-base text-emerald-50">
                    انتقال وجه از حساب‌های فرعی {{ $isGroupTransfer ? 'گروه' : 'شما' }} به حساب‌های فرعی خودتان یا دیگران
                </p>
            </div>
        </section>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        @if($subAccounts->count() === 0)
            <div class="nb-transfer-card p-6 text-center">
                <i class="fas fa-layer-group text-4xl text-slate-400 mb-3"></i>
                <p class="text-slate-600 mb-4">برای انتقال وجه باید حداقل یک حساب فرعی داشته باشید.</p>
                <a href="{{ route('najm-bahar.sub-accounts.create') }}" class="inline-flex items-center px-6 py-2 bg-earth-green text-white rounded-lg hover:bg-opacity-90 transition-colors">
                    <i class="fas fa-plus ml-2"></i>
                    ایجاد حساب فرعی
                </a>
            </div>
        @else
            <div class="nb-transfer-card p-6">
                <form action="{{ route($routePrefix . '.transfer.store', $routeParams) }}" method="POST" class="space-y-5">
                    @csrf
                    <div>
                        <label for="transaction_type" class="block text-sm font-semibold text-slate-700 mb-2">
                            نوع تراکنش
                        </label>
                        <select id="transaction_type" name="transaction_type" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" required>
                            <option value="immediate" {{ old('transaction_type', 'immediate') === 'immediate' ? 'selected' : '' }}>فوری</option>
                            <option value="scheduled" {{ old('transaction_type') === 'scheduled' ? 'selected' : '' }}>زمان بندی شده</option>
                        </select>
                    </div>

                    <div id="execute_at_field" class="hidden">
                        <label for="execute_at" class="block text-sm font-semibold text-slate-700 mb-2">
                            زمان اجرای تراکنش
                        </label>
                        <input type="datetime-local" id="execute_at" name="execute_at" value="{{ old('execute_at') }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <p class="text-xs text-slate-500 mt-1">تاریخ و زمان آینده را انتخاب کنید.</p>
                    </div>

                    <div>
                        <label for="source_sub_account_id" class="block text-sm font-semibold text-slate-700 mb-2">
                            حساب فرعی مبدا
                        </label>
                        <select id="source_sub_account_id" name="source_sub_account_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" required>
                            <option value="">انتخاب حساب فرعی</option>
                            @foreach($subAccounts as $subAccount)
                                @php
                                    $codeSlash = str_replace('-', '/', $subAccount->sub_account_code);
                                    $name = $subAccount->name;
                                    $nameHasCode = \Illuminate\Support\Str::contains($name, $subAccount->sub_account_code)
                                        || \Illuminate\Support\Str::contains($name, $codeSlash);
                                    if ($nameHasCode && ! \Illuminate\Support\Str::startsWith($name, 'حساب فرعی')) {
                                        $name = 'حساب فرعی ' . $codeSlash;
                                    }
                                @endphp
                                <option value="{{ $subAccount->id }}" data-balance="{{ \App\Helpers\BaharMoney::formatDecimal($subAccount->balance) }}" {{ old('source_sub_account_id') == $subAccount->id ? 'selected' : '' }}>
                                    {{ $name }} - {{ $codeSlash }} - {{ \App\Helpers\BaharMoney::formatDecimal($subAccount->balance) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1">موجودی هر حساب در کنار نام نمایش داده می شود.</p>
                        <p class="text-xs text-slate-600 mt-2">مانده انتخابی: <span id="source_balance_text">-</span></p>
                    </div>

                    <div>
                        <label for="target_sub_account_code" class="block text-sm font-semibold text-slate-700 mb-2">
                            کد حساب فرعی مقصد
                        </label>
                        <input type="text" id="target_sub_account_code" name="target_sub_account_code" value="{{ old('target_sub_account_code') }}" placeholder="مثال: 0000000000/001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" required>
                        <p class="text-xs text-slate-500 mt-1">می توانید / یا - وارد کنید.</p>
                        <div id="target_preview" class="mt-3 text-sm text-slate-600 hidden">
                            <div class="flex flex-col gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span id="target_owner_name">مالک: -</span>
                                <span id="target_sub_account_name">نام حساب: -</span>
                            </div>
                        </div>
                        <p id="target_preview_error" class="mt-2 text-xs text-red-600 hidden"></p>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-semibold text-slate-700 mb-2">
                            مبلغ (بهار)
                        </label>
                        <input type="number" id="amount" name="amount" value="{{ old('amount') }}" min="0.01" step="0.01" placeholder="مثال: 10.25" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" required>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-slate-700 mb-2">
                            توضیحات (اختیاری)
                        </label>
                        <textarea id="description" name="description" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="توضیحات انتقال" {{ $requireDescription ? 'required' : '' }}>{{ old('description') }}</textarea>
                        @if($requireDescription)
                            <p class="text-xs text-slate-500 mt-1">ثبت توضیحات برای انتقال‌های گروهی الزامی است.</p>
                        @endif
                    </div>

                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-semibold">
                        <i class="fas fa-paper-plane ml-2"></i>
                        انجام انتقال
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var transactionType = document.getElementById('transaction_type');
        var executeField = document.getElementById('execute_at_field');
        var executeInput = document.getElementById('execute_at');
        var sourceSelect = document.getElementById('source_sub_account_id');
        var sourceBalanceText = document.getElementById('source_balance_text');
        var targetInput = document.getElementById('target_sub_account_code');
        var previewBox = document.getElementById('target_preview');
        var previewError = document.getElementById('target_preview_error');
        var previewOwner = document.getElementById('target_owner_name');
        var previewSubAccount = document.getElementById('target_sub_account_name');

        if (!transactionType || !executeField || !executeInput || !sourceSelect || !sourceBalanceText || !targetInput) {
            return;
        }

        var toggleExecuteField = function () {
            var isScheduled = transactionType.value === 'scheduled';
            executeField.classList.toggle('hidden', !isScheduled);
            if (isScheduled) {
                executeInput.setAttribute('required', 'required');
            } else {
                executeInput.removeAttribute('required');
                executeInput.value = '';
            }
        };

        var updateBalanceText = function () {
            var selectedOption = sourceSelect.options[sourceSelect.selectedIndex];
            var balance = selectedOption ? selectedOption.getAttribute('data-balance') : null;
            sourceBalanceText.textContent = balance || '-';
        };

        transactionType.addEventListener('change', toggleExecuteField);
        sourceSelect.addEventListener('change', updateBalanceText);

        toggleExecuteField();
        updateBalanceText();

        var previewUrl = "{{ route($routePrefix . '.transfer.preview', $routeParams) }}";
        var previewTimer = null;

        var resetPreview = function () {
            previewBox.classList.add('hidden');
            previewError.classList.add('hidden');
            previewOwner.textContent = 'مالک: -';
            previewSubAccount.textContent = 'نام حساب: -';
        };

        var showPreviewError = function (message) {
            previewBox.classList.add('hidden');
            previewError.textContent = message;
            previewError.classList.remove('hidden');
        };

        var fetchPreview = function () {
            var value = targetInput.value.trim();
            if (!value) {
                resetPreview();
                return;
            }

            fetch(previewUrl + '?code=' + encodeURIComponent(value), {
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('not_found');
                }
                return response.json();
            }).then(function (data) {
                previewOwner.textContent = 'مالک: ' + (data.owner_name || '-');
                previewSubAccount.textContent = 'نام حساب: ' + (data.sub_account_name || '-');
                previewError.classList.add('hidden');
                previewBox.classList.remove('hidden');
            }).catch(function () {
                showPreviewError('حساب مقصد یافت نشد.');
            });
        };

        targetInput.addEventListener('input', function () {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(fetchPreview, 400);
        });

        if (targetInput.value.trim()) {
            fetchPreview();
        }
    });
</script>
@endpush
